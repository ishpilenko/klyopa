<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewsletterSubscription;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NewsletterSubscription>
 */
class NewsletterSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscription::class);
    }

    public function findByEmail(Site $site, string $email): ?NewsletterSubscription
    {
        return $this->createQueryBuilder('n')
            ->where('n.site = :site')
            ->andWhere('n.email = :email')
            ->setParameter('site', $site)
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
