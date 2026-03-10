<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Tag;
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

    /**
     * Find article by slug without using SiteContext (for API slug uniqueness checks).
     * Uses raw SQL to bypass the Doctrine SQL filter.
     */
    public function findBySlugUnfiltered(string $slug, int $siteId): ?Article
    {
        return $this->createQueryBuilder('a')
            ->where('a.slug = :slug')
            ->andWhere('a.site = :siteId')
            ->setParameter('slug', $slug)
            ->setParameter('siteId', $siteId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Flexible list query for API with optional filters.
     *
     * @return array{0: Article[], 1: int}
     */
    public function findForApi(
        ?ArticleStatus $status = null,
        ?Category $category = null,
        ?bool $isAiGenerated = null,
        ?int $olderThanDays = null,
        int $limit = 20,
        int $offset = 0,
    ): array {
        $site = $this->siteContext->getSite();

        $qb = $this->createQueryBuilder('a')
            ->where('a.site = :site')
            ->setParameter('site', $site);

        if ($status !== null) {
            $qb->andWhere('a.status = :status')->setParameter('status', $status);
        }
        if ($category !== null) {
            $qb->andWhere('a.category = :category')->setParameter('category', $category);
        }
        if ($isAiGenerated !== null) {
            $qb->andWhere('a.isAiGenerated = :aiGen')->setParameter('aiGen', $isAiGenerated);
        }
        if ($olderThanDays !== null) {
            $cutoff = new \DateTimeImmutable("-{$olderThanDays} days");
            $qb->andWhere('a.publishedAt < :cutoff')->setParameter('cutoff', $cutoff);
        }

        $total = (int) (clone $qb)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();

        $articles = $qb
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return [$articles, $total];
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

    /** @return Article[] */
    public function findPublishedSince(\DateTimeImmutable $since, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.site = :site')
            ->andWhere('a.status = :status')
            ->andWhere('a.publishedAt >= :since')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('status', \App\Enum\ArticleStatus::Published)
            ->setParameter('since', $since)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
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
    public function findByCategory(Category $category, int $limit = 20, int $offset = 0): array
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
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByCategory(Category $category): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.site = :site')
            ->andWhere('a.category = :category')
            ->andWhere('a.status = :status')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('category', $category)
            ->setParameter('status', ArticleStatus::Published)
            ->getQuery()
            ->getSingleScalarResult();
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

    /** Lightweight query for sitemap generation — only fetches needed fields */
    public function findForSitemap(int $offset = 0, int $limit = 1000): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.slug', 'a.updatedAt', 'a.publishedAt', 'IDENTITY(a.category) AS category_id')
            ->addSelect('c.slug AS category_slug')
            ->leftJoin('a.category', 'c')
            ->where('a.site = :site')
            ->andWhere('a.status = :status')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('a.updatedAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    /** Find articles related to a given one (same category if available, else recent from site) */
    public function findRelated(int $articleId, ?Category $category, int $limit = 3): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.site = :site')
            ->andWhere('a.status = :status')
            ->andWhere('a.id != :id')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('status', ArticleStatus::Published)
            ->setParameter('id', $articleId)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit);

        if ($category !== null) {
            $qb->andWhere('a.category = :category')
                ->setParameter('category', $category);
        }

        return $qb->getQuery()->getResult();
    }

    /** Next article in same category (older by publishedAt) */
    public function findNext(Article $article): ?Article
    {
        if ($article->getPublishedAt() === null) {
            return null;
        }

        $qb = $this->createQueryBuilder('a')
            ->where('a.site = :site')
            ->andWhere('a.status = :status')
            ->andWhere('a.id != :id')
            ->andWhere('a.publishedAt < :publishedAt')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('status', ArticleStatus::Published)
            ->setParameter('id', $article->getId())
            ->setParameter('publishedAt', $article->getPublishedAt())
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults(1);

        if ($article->getCategory() !== null) {
            $qb->andWhere('a.category = :category')
                ->setParameter('category', $article->getCategory());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /** Previous article in same category (newer by publishedAt) */
    public function findPrev(Article $article): ?Article
    {
        if ($article->getPublishedAt() === null) {
            return null;
        }

        $qb = $this->createQueryBuilder('a')
            ->where('a.site = :site')
            ->andWhere('a.status = :status')
            ->andWhere('a.id != :id')
            ->andWhere('a.publishedAt > :publishedAt')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('status', ArticleStatus::Published)
            ->setParameter('id', $article->getId())
            ->setParameter('publishedAt', $article->getPublishedAt())
            ->orderBy('a.publishedAt', 'ASC')
            ->setMaxResults(1);

        if ($article->getCategory() !== null) {
            $qb->andWhere('a.category = :category')
                ->setParameter('category', $article->getCategory());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /** @return Article[] */
    public function findByTag(Tag $tag, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.tags', 't')
            ->where('a.site = :site')
            ->andWhere('a.status = :status')
            ->andWhere('t = :tag')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('status', ArticleStatus::Published)
            ->setParameter('tag', $tag)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByTag(Tag $tag): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->join('a.tags', 't')
            ->where('a.site = :site')
            ->andWhere('a.status = :status')
            ->andWhere('t = :tag')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('status', ArticleStatus::Published)
            ->setParameter('tag', $tag)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
