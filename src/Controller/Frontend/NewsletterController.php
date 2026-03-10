<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Entity\NewsletterSubscription;
use App\Repository\NewsletterSubscriptionRepository;
use App\Service\SiteContext;
use App\Service\TurnstileVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validation;

#[Route('/newsletter/subscribe', name: 'newsletter_subscribe', methods: ['POST'])]
class NewsletterController extends AbstractController
{
    private const SESSION_KEY       = 'newsletter_submit_count';
    private const MAX_PER_SESSION   = 3;          // hard cap per session
    private const CAPTCHA_THRESHOLD = 1;           // require captcha after Nth submit

    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly EntityManagerInterface $em,
        private readonly NewsletterSubscriptionRepository $subscriptionRepo,
        private readonly TurnstileVerifier $turnstile,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        // ── CSRF ──────────────────────────────────────────────────────────────
        $csrfToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('newsletter', $csrfToken)) {
            return $this->json(['error' => 'Invalid request.'], 400);
        }

        // ── Session counter ────────────────────────────────────────────────────
        $session = $request->getSession();
        $submitCount = (int) $session->get(self::SESSION_KEY, 0);

        // Hard cap: prevent even legitimate multi-submit spam
        if ($submitCount >= self::MAX_PER_SESSION) {
            return $this->json(['error' => 'Too many attempts this session. Please try again later.'], 429);
        }

        // ── Captcha (required on 2nd+ submit in session) ───────────────────────
        if ($submitCount >= self::CAPTCHA_THRESHOLD) {
            $token = $request->request->get('cf-turnstile-response', '');
            if (!$this->turnstile->verify($token, $request->getClientIp() ?? '')) {
                return $this->json([
                    'error'           => 'Please complete the captcha.',
                    'captcha_required' => true,
                ], 400);
            }
        }

        // ── Email validation ───────────────────────────────────────────────────
        $email = strtolower(trim((string) $request->request->get('email', '')));

        $validator  = Validation::createValidator();
        $violations = $validator->validate($email, [new Email(['mode' => 'html5'])]);

        if (count($violations) > 0 || $email === '') {
            return $this->json(['error' => 'Please enter a valid email address.'], 422);
        }

        // ── Save (idempotent — same email returns success silently) ────────────
        $site = $this->siteContext->getSite();

        $existing = $this->subscriptionRepo->findByEmail($site, $email);

        if ($existing === null) {
            $subscription = (new NewsletterSubscription())
                ->setSite($site)
                ->setEmail($email)
                ->setIpAddress($request->getClientIp());

            $this->em->persist($subscription);
            $this->em->flush();
        }
        // If already subscribed — return success anyway (don't leak existence)

        // ── Increment session counter ──────────────────────────────────────────
        $session->set(self::SESSION_KEY, $submitCount + 1);

        return $this->json(['success' => true, 'message' => 'You\'re subscribed! 🎉']);
    }
}
