<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Redirect;
use App\Service\SiteContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Redirect>
 */
class RedirectRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SiteContext $siteContext,
    ) {
        parent::__construct($registry, Redirect::class);
    }

    public function findBySourcePath(string $path): ?Redirect
    {
        return $this->createQueryBuilder('r')
            ->where('r.site = :site')
            ->andWhere('r.sourcePath = :path')
            ->setParameter('site', $this->siteContext->getSite())
            ->setParameter('path', $path)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
