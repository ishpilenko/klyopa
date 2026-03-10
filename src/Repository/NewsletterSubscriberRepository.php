<?php
declare(strict_types=1);
namespace App\Repository;

use App\Entity\NewsletterSubscriber;
use App\Entity\Site;
use App\Enum\SubscriberStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NewsletterSubscriber> */
class NewsletterSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscriber::class);
    }

    public function findByToken(string $token): ?NewsletterSubscriber
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function findByEmail(Site $site, string $email): ?NewsletterSubscriber
    {
        return $this->findOneBy(['site' => $site, 'email' => $email]);
    }

    /** @return NewsletterSubscriber[] */
    public function findActive(Site $site): array
    {
        return $this->findBy(['site' => $site, 'status' => SubscriberStatus::Active]);
    }

    public function getStatsBySite(Site $site): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.status, COUNT(s.id) as cnt')
            ->where('s.site = :site')
            ->setParameter('site', $site)
            ->groupBy('s.status')
            ->getQuery()
            ->getArrayResult();

        $stats = ['total' => 0, 'active' => 0, 'pending' => 0, 'unsubscribed' => 0, 'bounced' => 0];
        foreach ($rows as $row) {
            $key = $row['status'] instanceof SubscriberStatus ? $row['status']->value : (string) $row['status'];
            $stats[$key] = (int) $row['cnt'];
            $stats['total'] += (int) $row['cnt'];
        }
        return $stats;
    }
}
