<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Marks an entity as belonging to a specific site.
 * Used by Doctrine SiteFilter to automatically scope all queries.
 */
interface SiteAwareInterface
{
    public function getSite(): Site;

    public function setSite(Site $site): static;
}
