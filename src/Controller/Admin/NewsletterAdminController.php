<?php
declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\NewsletterIssue;
use App\Entity\NewsletterSubscriber;
use App\Enum\IssueStatus;
use App\Repository\NewsletterIssueRepository;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\Newsletter\DigestGenerator;
use App\Service\Newsletter\NewsletterMailer;
use App\Service\Newsletter\NewsletterSendService;
use App\Service\SiteContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/newsletter', name: 'admin_newsletter_')]
class NewsletterAdminController extends AbstractController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly EntityManagerInterface $em,
        private readonly NewsletterSubscriberRepository $subscriberRepo,
        private readonly NewsletterIssueRepository $issueRepo,
        private readonly NewsletterMailer $mailer,
        private readonly DigestGenerator $digestGenerator,
        private readonly NewsletterSendService $sendService,
    ) {
    }

    // ── Subscribers ──────────────────────────────────────────────────────────

    #[Route('/subscribers', name: 'subscribers', methods: ['GET'])]
    public function subscribers(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $site   = $this->siteContext->getSite();
        $status = $request->query->get('status');
        $search = $request->query->get('q', '');
        $page   = max(1, $request->query->getInt('page', 1));
        $perPage = 50;

        $qb = $this->subscriberRepo->createQueryBuilder('s')
            ->where('s.site = :site')
            ->setParameter('site', $site)
            ->orderBy('s.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('s.status = :status')->setParameter('status', $status);
        }
        if ($search) {
            $qb->andWhere('s.email LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        $total       = (int) (clone $qb)->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();
        $subscribers = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();
        $stats       = $this->subscriberRepo->getStatsBySite($site);

        return $this->render('admin/newsletter/subscribers.html.twig', [
            'subscribers' => $subscribers,
            'stats'       => $stats,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'totalPages'  => (int) ceil($total / $perPage),
            'status'      => $status,
            'search'      => $search,
            'site'        => $site,
        ]);
    }

    #[Route('/subscribers/export', name: 'export', methods: ['GET'])]
    public function export(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $subscribers = $this->subscriberRepo->findActive($this->siteContext->getSite());

        $csv = "email,confirmed_at,source\n";
        foreach ($subscribers as $s) {
            $csv .= $s->getEmail() . ',' . ($s->getConfirmedAt()?->format('Y-m-d') ?? '') . ',' . $s->getSource() . "\n";
        }

        return new Response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="subscribers_' . date('Y-m-d') . '.csv"',
        ]);
    }

    #[Route('/subscribers/{id}/unsubscribe', name: 'unsubscribe_manual', methods: ['POST'])]
    public function unsubscribeManual(NewsletterSubscriber $subscriber): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $subscriber->unsubscribe();
        $this->em->flush();
        $this->addFlash('success', $subscriber->getEmail() . ' has been unsubscribed.');
        return $this->redirectToRoute('admin_newsletter_subscribers');
    }

    // ── Issues ────────────────────────────────────────────────────────────────

    #[Route('/issues', name: 'issues', methods: ['GET'])]
    public function issues(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $issues = $this->issueRepo->findBy(
            ['site' => $this->siteContext->getSite()],
            ['createdAt' => 'DESC'],
        );

        return $this->render('admin/newsletter/issues.html.twig', [
            'issues' => $issues,
            'site'   => $this->siteContext->getSite(),
        ]);
    }

    #[Route('/issues/generate', name: 'generate', methods: ['POST'])]
    public function generate(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $issue = $this->digestGenerator->generate();
        $this->em->persist($issue);
        $this->em->flush();

        $this->addFlash('success', 'Digest generated. Review and edit before sending.');
        return $this->redirectToRoute('admin_newsletter_edit', ['id' => $issue->getId()]);
    }

    #[Route('/issues/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(NewsletterIssue $issue, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            $issue->setSubject((string) $request->request->get('subject', $issue->getSubject()));
            $issue->setPreviewText($request->request->get('preview_text') ?: null);
            $issue->setContentHtml((string) $request->request->get('content_html', ''));
            $issue->setStatus(IssueStatus::Ready);
            $this->em->flush();

            $this->addFlash('success', 'Issue saved and marked as ready.');
            return $this->redirectToRoute('admin_newsletter_edit', ['id' => $issue->getId()]);
        }

        return $this->render('admin/newsletter/edit.html.twig', [
            'issue' => $issue,
            'site'  => $this->siteContext->getSite(),
        ]);
    }

    #[Route('/issues/{id}/preview', name: 'preview', methods: ['GET'])]
    public function preview(NewsletterIssue $issue): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $html = str_replace('{{UNSUBSCRIBE_URL}}', '#preview', $issue->getContentHtml());
        return new Response($html);
    }

    #[Route('/issues/{id}/send-test', name: 'send_test', methods: ['POST'])]
    public function sendTest(NewsletterIssue $issue, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $testEmail = (string) $request->request->get('email', '');
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email address.'], 400);
        }

        $testSubscriber = (new NewsletterSubscriber())
            ->setSite($issue->getSite())
            ->setEmail($testEmail);

        // Use reflection to set token since it's set in constructor
        try {
            $this->mailer->sendIssue($issue, $testSubscriber);
            return $this->json(['message' => 'Test email sent to ' . $testEmail]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/issues/{id}/send', name: 'send', methods: ['POST'])]
    public function send(NewsletterIssue $issue): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($issue->getStatus() !== IssueStatus::Ready) {
            $this->addFlash('danger', 'Issue must be in "ready" status to send.');
            return $this->redirectToRoute('admin_newsletter_edit', ['id' => $issue->getId()]);
        }

        $this->sendService->dispatch($issue);

        $this->addFlash('success', 'Sending started. Messages are in the queue.');
        return $this->redirectToRoute('admin_newsletter_issues');
    }
}
