<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Service\SiteContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SiteContext $siteContext,
    ) {
        parent::__construct($registry, Category::class);
    }

    /** @return Category[] */
    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.site = :site')
            ->andWhere('c.isActive = true')
            ->andWhere('c.parent IS NULL')
            ->setParameter('site', $this->siteContext->getSite())
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->createQueryBuilder('c')
            ->where('c.site = :site')
            ->andWhere('c.slug = :slug')
            ->andWhere('c.isActive = true')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
