<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Category;
use App\Enum\ArticleStatus;
use App\Service\SiteContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SiteContext $siteContext,
    ) {
        parent::__construct($registry, Article::class);
    }

    /** @return Article[] */
    public function findPublished(int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.site = :site')
            ->andWhere('a.status = :status')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?Article
    {
        return $this->createQueryBuilder('a')
            ->where('a.site = :site')
            ->andWhere('a.slug = :slug')
            ->andWhere('a.status = :status')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('slug', $slug)
            ->setParameter('status', ArticleStatus::Published)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Article[] */
    public function findByCategory(Category $category, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.site = :site')
            ->andWhere('a.category = :category')
            ->andWhere('a.status = :status')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('category', $category)
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countPublished(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.site = :site')
            ->andWhere('a.status = :status')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('status', ArticleStatus::Published)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
