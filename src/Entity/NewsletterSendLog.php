<?php
declare(strict_types=1);
namespace App\Entity;

use App\Repository\NewsletterSendLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NewsletterSendLogRepository::class)]
#[ORM\Table(name: 'newsletter_send_log')]
#[ORM\UniqueConstraint(name: 'uniq_issue_subscriber', columns: ['issue_id', 'subscriber_id'])]
#[ORM\Index(name: 'idx_issue_status', columns: ['issue_id', 'status'])]
class NewsletterSendLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: NewsletterIssue::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private NewsletterIssue $issue;

    #[ORM\ManyToOne(targetEntity: NewsletterSubscriber::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private NewsletterSubscriber $subscriber;

    #[ORM\Column(length: 10, options: ['default' => 'queued'])]
    private string $status = 'queued';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    public function getId(): int { return $this->id; }
    public function getIssue(): NewsletterIssue { return $this->issue; }
    public function setIssue(NewsletterIssue $i): static { $this->issue = $i; return $this; }
    public function getSubscriber(): NewsletterSubscriber { return $this->subscriber; }
    public function setSubscriber(NewsletterSubscriber $s): static { $this->subscriber = $s; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): static { $this->status = $s; return $this; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $m): static { $this->errorMessage = $m; return $this; }
    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function setSentAt(?\DateTimeImmutable $t): static { $this->sentAt = $t; return $this; }
}
