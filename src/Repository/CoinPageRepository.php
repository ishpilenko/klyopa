<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CoinPage;
use App\Service\SiteContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CoinPageRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SiteContext $siteContext,
    ) {
        parent::__construct($registry, CoinPage::class);
    }

    public function findBySlug(string $slug): ?CoinPage
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findByCoinGeckoId(string $id): ?CoinPage
    {
        return $this->findOneBy(['coinGeckoId' => $id]);
    }

    /** @return CoinPage[] */
    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return CoinPage[] */
    public function findForSitemap(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.slug', 'c.updatedAt')
            ->where('c.isActive = true')
            ->getQuery()
            ->getArrayResult();
    }
}
