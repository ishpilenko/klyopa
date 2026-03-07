<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Repository\SiteRepository;
use App\Service\SiteContext;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resolves the current site from the request Host header.
 * Runs early (priority 255) so all subsequent listeners/controllers
 * have access to SiteContext.
 */
class SiteResolverSubscriber implements EventSubscriberInterface
{
    private const CACHE_PREFIX = 'site_by_domain_';
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly SiteRepository $siteRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 255],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $host = $request->getHost();

        // Skip Symfony profiler and dev routes
        $pathInfo = $request->getPathInfo();
        if (str_starts_with($pathInfo, '/_')) {
            return;
        }

        $site = $this->resolveSite($host);

        if (null === $site) {
            throw new NotFoundHttpException(
                sprintf('No active site found for domain "%s".', $host)
            );
        }

        $this->siteContext->setSite($site);

        // Enable Doctrine SiteFilter for all subsequent queries
        $filter = $this->entityManager->getFilters()->enable('site_filter');
        $filter->setParameter('siteId', $site->getId());

        // Set request locale from site config
        $request->setLocale($site->getLocale());
    }

    private function resolveSite(string $host): ?\App\Entity\Site
    {
        $cacheKey = self::CACHE_PREFIX . md5($host);
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            $siteId = $item->get();

            // Load from DB (Doctrine identity map will cache in memory)
            return $this->siteRepository->find($siteId);
        }

        $site = $this->siteRepository->findByDomain($host);

        if (null !== $site) {
            $item->set($site->getId());
            $item->expiresAfter(self::CACHE_TTL);
            $this->cache->save($item);
        }

        return $site;
    }
}
