<?php
declare(strict_types=1);
namespace App\Controller\Frontend;

use App\Entity\NewsletterSubscriber;
use App\Enum\SubscriberStatus;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\Newsletter\NewsletterMailer;
use App\Service\SiteContext;
use App\Service\TurnstileVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NewsletterController extends AbstractController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly EntityManagerInterface $em,
        private readonly NewsletterSubscriberRepository $subscriberRepo,
        private readonly NewsletterMailer $mailer,
        private readonly TurnstileVerifier $turnstile,
    ) {
    }

    #[Route('/newsletter/subscribe', name: 'newsletter_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        // Accept both JSON and form-encoded
        $contentType = $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $data    = json_decode($request->getContent(), true) ?? [];
            $email   = trim((string) ($data['email'] ?? ''));
            $source  = (string) ($data['source'] ?? 'homepage');
            $cfToken = (string) ($data['cf-turnstile-response'] ?? '');
        } else {
            $email   = trim((string) $request->request->get('email', ''));
            $source  = (string) $request->request->get('source', 'homepage');
            $cfToken = (string) $request->request->get('cf-turnstile-response', '');
        }

        // Session-based captcha gate (2nd+ submit)
        $session      = $request->getSession();
        $submitCount  = (int) $session->get('nl_submit_count', 0);

        if ($submitCount >= 1) {
            if (!$this->turnstile->verify($cfToken ?: null, $request->getClientIp() ?? '')) {
                return $this->json(['error' => 'Please complete the captcha.', 'captcha_required' => true], 400);
            }
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Please enter a valid email address.'], 400);
        }

        $site     = $this->siteContext->getSite();
        $existing = $this->subscriberRepo->findByEmail($site, strtolower($email));

        if ($existing) {
            if ($existing->getStatus() === SubscriberStatus::Active) {
                $session->set('nl_submit_count', $submitCount + 1);
                return $this->json(['message' => 'This email is already subscribed.']);
            }
            if ($existing->getStatus() === SubscriberStatus::Pending) {
                $this->mailer->sendConfirmation($existing);
                $session->set('nl_submit_count', $submitCount + 1);
                return $this->json(['message' => 'Confirmation email resent. Check your inbox.']);
            }
            // Resubscribe after unsubscribe
            $existing->resubscribe();
            $this->em->flush();
            $this->mailer->sendConfirmation($existing);
            $session->set('nl_submit_count', $submitCount + 1);
            return $this->json(['message' => 'Check your email to reconfirm your subscription.']);
        }

        $subscriber = (new NewsletterSubscriber())
            ->setSite($site)
            ->setEmail(strtolower($email))
            ->setSource($source)
            ->setIpAddress($request->getClientIp())
            ->setUserAgent($request->headers->get('User-Agent'));

        $this->em->persist($subscriber);
        $this->em->flush();

        $this->mailer->sendConfirmation($subscriber);

        $session->set('nl_submit_count', $submitCount + 1);

        return $this->json(['message' => 'Check your email to confirm your subscription.']);
    }

    #[Route('/newsletter/confirm/{token}', name: 'newsletter_confirm', methods: ['GET'])]
    public function confirm(string $token): Response
    {
        $subscriber = $this->subscriberRepo->findByToken($token);

        if (!$subscriber) {
            throw $this->createNotFoundException('Invalid confirmation link.');
        }

        if ($subscriber->getStatus() === SubscriberStatus::Active) {
            return $this->render('frontend/newsletter/already_confirmed.html.twig', ['site' => $this->siteContext->getSite()]);
        }

        $subscriber->confirm();
        $this->em->flush();

        return $this->render('frontend/newsletter/confirmed.html.twig', [
            'subscriber' => $subscriber,
            'site'       => $this->siteContext->getSite(),
        ]);
    }

    #[Route('/newsletter/unsubscribe/{token}', name: 'newsletter_unsubscribe', methods: ['GET'])]
    public function unsubscribe(string $token): Response
    {
        $subscriber = $this->subscriberRepo->findByToken($token);

        if (!$subscriber) {
            throw $this->createNotFoundException('Invalid unsubscribe link.');
        }

        $subscriber->unsubscribe();
        $this->em->flush();

        return $this->render('frontend/newsletter/unsubscribed.html.twig', [
            'subscriber' => $subscriber,
            'site'       => $this->siteContext->getSite(),
        ]);
    }
}
