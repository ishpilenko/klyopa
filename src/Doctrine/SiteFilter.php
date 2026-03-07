<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\SiteAwareInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Doctrine SQL Filter that automatically appends a WHERE site_id = :siteId
 * condition to every SELECT query for entities implementing SiteAwareInterface.
 *
 * Enabled per-request by SiteResolverSubscriber after the current site is resolved.
 */
class SiteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        // Only apply to entities that belong to a site
        if (!$targetEntity->reflClass->implementsInterface(SiteAwareInterface::class)) {
            return '';
        }

        // The parameter is set by SiteResolverSubscriber
        try {
            $siteId = $this->getParameter('siteId');
        } catch (\InvalidArgumentException) {
            // Parameter not set yet — skip filtering (e.g., during warmup)
            return '';
        }

        return sprintf('%s.site_id = %s', $targetTableAlias, $siteId);
    }
}
