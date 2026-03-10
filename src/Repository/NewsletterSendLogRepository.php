<?php
declare(strict_types=1);
namespace App\Repository;

use App\Entity\NewsletterSendLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NewsletterSendLog> */
class NewsletterSendLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSendLog::class);
    }
}
