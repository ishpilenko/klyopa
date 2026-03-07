<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tool;
use App\Service\SiteContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tool>
 */
class ToolRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SiteContext $siteContext,
    ) {
        parent::__construct($registry, Tool::class);
    }

    /** @return Tool[] */
    public function findActive(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.site = :site')
            ->andWhere('t.isActive = true')
            ->setParameter('site', $this->siteContext->getSite())
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?Tool
    {
        return $this->createQueryBuilder('t')
            ->where('t.site = :site')
            ->andWhere('t.slug = :slug')
            ->andWhere('t.isActive = true')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
