<?php
declare(strict_types=1);
namespace App\MessageHandler;

use App\Enum\IssueStatus;
use App\Enum\SubscriberStatus;
use App\Message\SendNewsletterMessage;
use App\Repository\NewsletterIssueRepository;
use App\Repository\NewsletterSendLogRepository;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\Newsletter\NewsletterMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendNewsletterHandler
{
    public function __construct(
        private readonly NewsletterIssueRepository      $issueRepo,
        private readonly NewsletterSubscriberRepository $subscriberRepo,
        private readonly NewsletterSendLogRepository    $logRepo,
        private readonly NewsletterMailer               $mailer,
        private readonly EntityManagerInterface         $em,
    ) {
    }

    public function __invoke(SendNewsletterMessage $message): void
    {
        $issue      = $this->issueRepo->find($message->issueId);
        $subscriber = $this->subscriberRepo->find($message->subscriberId);

        if (!$issue || !$subscriber) {
            return;
        }

        if ($subscriber->getStatus() !== SubscriberStatus::Active) {
            return;
        }

        $log = $this->logRepo->findOneBy(['issue' => $issue, 'subscriber' => $subscriber]);

        try {
            $this->mailer->sendIssue($issue, $subscriber);
            $log?->setStatus('sent');
            $log?->setSentAt(new \DateTimeImmutable());
            $issue->setSentCount($issue->getSentCount() + 1);
        } catch (\Throwable $e) {
            $log?->setStatus('failed');
            $log?->setErrorMessage($e->getMessage());
            $issue->setFailedCount($issue->getFailedCount() + 1);

            if ($this->isBounce($e)) {
                $subscriber->setStatus(SubscriberStatus::Bounced);
            }
        }

        $this->em->flush();

        $totalProcessed = $issue->getSentCount() + $issue->getFailedCount();
        if ($totalProcessed >= $issue->getRecipientsCount() && $issue->getRecipientsCount() > 0) {
            $issue->setStatus(IssueStatus::Sent);
            $this->em->flush();
        }
    }

    private function isBounce(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        return str_contains($msg, 'Mailbox not found')
            || str_contains($msg, 'User unknown')
            || str_contains($msg, '550');
    }
}
