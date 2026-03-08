<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AffiliateLink;
use App\Enum\PartnerType;
use App\Service\SiteContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AffiliateLink>
 */
class AffiliateLinkRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SiteContext $siteContext,
    ) {
        parent::__construct($registry, AffiliateLink::class);
    }

    public function findByPartner(string $partner): ?AffiliateLink
    {
        return $this->createQueryBuilder('a')
            ->where('a.site = :site')
            ->andWhere('a.partner = :partner')
            ->andWhere('a.isActive = true')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('partner', $partner)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return AffiliateLink[] */
    public function findActive(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.site = :site')
            ->andWhere('a.isActive = true')
            ->setParameter('site', $this->siteContext->getSite())
            ->orderBy('a.displayName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return AffiliateLink[] */
    public function findActiveByType(PartnerType $type): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.site = :site')
            ->andWhere('a.partnerType = :type')
            ->andWhere('a.isActive = true')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('type', $type)
            ->orderBy('a.displayName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
