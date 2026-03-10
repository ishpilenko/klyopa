<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NewsletterSubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NewsletterSubscriptionRepository::class)]
#[ORM\Table(name: 'newsletter_subscriptions')]
#[ORM\UniqueConstraint(name: 'uniq_site_email', columns: ['site_id', 'email'])]
#[ORM\Index(columns: ['site_id', 'status'], name: 'idx_site_status')]
class NewsletterSubscription implements SiteAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 20, options: ['default' => 'active'])]
    private string $status = 'active';

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int { return $this->id; }

    public function getSite(): Site { return $this->site; }
    public function setSite(Site $site): static { $this->site = $site; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ip): static { $this->ipAddress = $ip; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
