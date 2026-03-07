<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContentQueue;
use App\Enum\ContentQueueStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContentQueue>
 */
class ContentQueueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentQueue::class);
    }

    /** @return ContentQueue[] */
    public function findPending(int $limit = 10): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.status = :status')
            ->setParameter('status', ContentQueueStatus::Pending)
            ->orderBy('q.priority', 'DESC')
            ->addOrderBy('q.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return ContentQueue[] */
    public function findPendingBySite(int $siteId, int $limit = 10): array
    {
        return $this->createQueryBuilder('q')
            ->join('q.site', 's')
            ->where('q.status = :status')
            ->andWhere('s.id = :siteId')
            ->setParameter('status', ContentQueueStatus::Pending)
            ->setParameter('siteId', $siteId)
            ->orderBy('q.priority', 'DESC')
            ->addOrderBy('q.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
