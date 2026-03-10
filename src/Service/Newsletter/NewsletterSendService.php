<?php
declare(strict_types=1);
namespace App\Service\Newsletter;

use App\Entity\NewsletterIssue;
use App\Entity\NewsletterSendLog;
use App\Enum\IssueStatus;
use App\Message\SendNewsletterMessage;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class NewsletterSendService
{
    public function __construct(
        private readonly NewsletterSubscriberRepository $subscriberRepo,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function dispatch(NewsletterIssue $issue): void
    {
        $subscribers = $this->subscriberRepo->findActive($issue->getSite());

        $issue->setStatus(IssueStatus::Sending);
        $issue->setRecipientsCount(count($subscribers));
        $issue->setSentAt(new \DateTimeImmutable());
        $this->em->flush();

        foreach ($subscribers as $subscriber) {
            $log = new NewsletterSendLog();
            $log->setIssue($issue);
            $log->setSubscriber($subscriber);
            $log->setStatus('queued');
            $this->em->persist($log);

            $this->bus->dispatch(new SendNewsletterMessage(
                issueId:      $issue->getId(),
                subscriberId: $subscriber->getId(),
            ));
        }

        $this->em->flush();
    }
}
