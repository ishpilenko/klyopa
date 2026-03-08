<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GlossaryTerm;
use App\Service\SiteContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GlossaryTerm>
 */
class GlossaryTermRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SiteContext $siteContext,
    ) {
        parent::__construct($registry, GlossaryTerm::class);
    }

    public function findBySlug(string $slug): ?GlossaryTerm
    {
        return $this->createQueryBuilder('g')
            ->where('g.site = :site')
            ->andWhere('g.slug = :slug')
            ->andWhere('g.status = :status')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('slug', $slug)
            ->setParameter('status', 'published')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns all published terms grouped by first letter.
     * @return array<string, GlossaryTerm[]>
     */
    public function findAllGroupedByLetter(): array
    {
        $terms = $this->createQueryBuilder('g')
            ->where('g.site = :site')
            ->andWhere('g.status = :status')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('status', 'published')
            ->orderBy('g.firstLetter', 'ASC')
            ->addOrderBy('g.term', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($terms as $term) {
            $grouped[$term->getFirstLetter()][] = $term;
        }

        return $grouped;
    }

    /**
     * Find multiple terms by their slugs (for "related terms" section).
     * @param  string[] $slugs
     * @return GlossaryTerm[]
     */
    public function findBySlugs(array $slugs): array
    {
        if (empty($slugs)) {
            return [];
        }

        return $this->createQueryBuilder('g')
            ->where('g.site = :site')
            ->andWhere('g.slug IN (:slugs)')
            ->andWhere('g.status = :status')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('slugs', $slugs)
            ->setParameter('status', 'published')
            ->orderBy('g.term', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return GlossaryTerm[] */
    public function findForSitemap(): array
    {
        return $this->createQueryBuilder('g')
            ->select('g.slug', 'g.updatedAt')
            ->where('g.site = :site')
            ->andWhere('g.status = :status')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('status', 'published')
            ->orderBy('g.term', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function countPublished(): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.site = :site')
            ->andWhere('g.status = :status')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('status', 'published')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
