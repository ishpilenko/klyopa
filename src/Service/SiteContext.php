<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Site;

/**
 * Holds the current site for the duration of a request.
 * Set by SiteResolverSubscriber on kernel.request.
 * Injected everywhere site-specific data is needed.
 */
class SiteContext
{
    private ?Site $site = null;

    public function setSite(Site $site): void
    {
        $this->site = $site;
    }

    public function getSite(): Site
    {
        if (null === $this->site) {
            throw new \RuntimeException(
                'SiteContext has not been initialized. '
                . 'Ensure SiteResolverSubscriber ran before accessing SiteContext.'
            );
        }

        return $this->site;
    }

    public function hasSite(): bool
    {
        return null !== $this->site;
    }

    public function reset(): void
    {
        $this->site = null;
    }
}
