<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\SiteContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PageCacheSubscriber implements EventSubscriberInterface
{
    // TTL per route prefix (seconds)
    private const TTL_HOME = 60;
    private const TTL_CATEGORY = 300;
    private const TTL_ARTICLE = 900;
    private const TTL_DEFAULT = 120;

    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly CacheInterface $cache,
        private readonly string $appEnv,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 10],   // after SiteResolver (255) and Redirect (20)
            KernelEvents::RESPONSE => ['onResponse', -10],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        if ($this->appEnv === 'dev') {
            return;
        }
        if (!$this->siteContext->hasSite()) {
            return;
        }

        $request = $event->getRequest();

        // Only cache GET requests
        if ($request->getMethod() !== 'GET') {
            return;
        }

        // Don't cache admin/api
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/admin') || str_starts_with($path, '/api')) {
            return;
        }

        $cacheKey = $this->buildKey($request->getPathInfo(), $request->getQueryString());

        $item = $this->cache->getItem($cacheKey);
        if ($item->isHit()) {
            /** @var array{status: int, headers: array, content: string} $cached */
            $cached = $item->get();
            $response = new Response($cached['content'], $cached['status'], $cached['headers']);
            $response->headers->set('X-Cache', 'HIT');
            $event->setResponse($response);
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        if ($this->appEnv === 'dev') {
            return;
        }
        if (!$this->siteContext->hasSite()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if ($request->getMethod() !== 'GET') {
            return;
        }

        $path = $request->getPathInfo();
        if (str_starts_with($path, '/admin') || str_starts_with($path, '/api')) {
            return;
        }

        // Only cache successful HTML responses
        if ($response->getStatusCode() !== 200) {
            return;
        }
        if (!str_contains((string) $response->headers->get('Content-Type', ''), 'text/html')) {
            return;
        }
        // Don't cache if already from cache
        if ($response->headers->has('X-Cache')) {
            return;
        }

        $ttl = $this->resolveTtl($path);
        $cacheKey = $this->buildKey($path, $request->getQueryString());

        $item = $this->cache->getItem($cacheKey);
        $item->set([
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'content' => $response->getContent(),
        ]);
        $item->expiresAfter($ttl);
        $this->cache->save($item);

        $response->headers->set('X-Cache', 'MISS');
    }

    public function invalidate(int $siteId, string $path): void
    {
        $key = $this->buildKey($path, null, $siteId);
        $this->cache->delete($key);
    }

    public function invalidateByPrefix(int $siteId, string $pathPrefix): void
    {
        // Symfony Cache doesn't support wildcard delete on all adapters.
        // For Redis adapter, we use tag-based invalidation or accept eventual consistency.
        // Here we delete exact keys we know about (home + the specific path).
        $this->cache->delete($this->buildKey('/', null, $siteId));
        $this->cache->delete($this->buildKey($pathPrefix, null, $siteId));
    }

    private function buildKey(string $path, ?string $query, ?int $siteId = null): string
    {
        $id = $siteId ?? ($this->siteContext->hasSite() ? $this->siteContext->getSite()->getId() : 0);
        $suffix = $query ? '?' . $query : '';
        return 'fpc_' . $id . '_' . hash('xxh64', $path . $suffix);
    }

    private function resolveTtl(string $path): int
    {
        if ($path === '/') {
            return self::TTL_HOME;
        }
        // /{category}/ pattern
        if (preg_match('#^/[a-z0-9-]+/$#', $path)) {
            return self::TTL_CATEGORY;
        }
        // /{category}/{article} pattern
        if (preg_match('#^/[a-z0-9-]+/[a-z0-9-]+$#', $path)) {
            return self::TTL_ARTICLE;
        }
        return self::TTL_DEFAULT;
    }
}
