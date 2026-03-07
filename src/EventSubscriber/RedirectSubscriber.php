<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Repository\RedirectRepository;
use App\Service\SiteContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly RedirectRepository $redirectRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After SiteResolverSubscriber (255) but before controllers (0)
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->siteContext->hasSite()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        $redirect = $this->redirectRepository->findBySourcePath($path);
        if ($redirect === null) {
            return;
        }

        // Increment hit counter asynchronously (best-effort)
        try {
            $redirect->incrementHits();
            $this->em->flush();
        } catch (\Throwable) {
            // Non-critical — don't fail the redirect
        }

        $event->setResponse(new RedirectResponse(
            $redirect->getTargetPath(),
            $redirect->getStatusCode(),
        ));
    }
}
