<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ExchangeData;
use App\Service\SiteContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExchangeData>
 */
class ExchangeDataRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SiteContext $siteContext,
    ) {
        parent::__construct($registry, ExchangeData::class);
    }

    public function findBySlug(string $slug): ?ExchangeData
    {
        return $this->createQueryBuilder('e')
            ->where('e.site = :site')
            ->andWhere('e.slug = :slug')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return ExchangeData[] */
    public function findAll(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.site = :site')
            ->setParameter('site', $this->siteContext->getSite())
            ->orderBy('e.rating', 'DESC')
            ->addOrderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return ExchangeData[] */
    public function findTopByRating(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.site = :site')
            ->andWhere('e.rating IS NOT NULL')
            ->setParameter('site', $this->siteContext->getSite())
            ->orderBy('e.rating', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
