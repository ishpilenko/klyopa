<?php
declare(strict_types=1);
namespace App\Entity;

use App\Enum\SubscriberStatus;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NewsletterSubscriberRepository::class)]
#[ORM\Table(name: 'newsletter_subscribers')]
#[ORM\UniqueConstraint(name: 'uniq_site_email', columns: ['site_id', 'email'])]
#[ORM\Index(name: 'idx_site_status', columns: ['site_id', 'status'])]
#[ORM\Index(name: 'idx_token', columns: ['token'])]
#[ORM\HasLifecycleCallbacks]
class NewsletterSubscriber implements SiteAwareInterface
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

    #[ORM\Column(type: 'string', enumType: SubscriberStatus::class, options: ['default' => 'pending'])]
    private SubscriberStatus $status = SubscriberStatus::Pending;

    #[ORM\Column(length: 64)]
    private string $token;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $unsubscribedAt = null;

    #[ORM\Column(length: 50, options: ['default' => 'homepage'])]
    private string $source = 'homepage';

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->token     = bin2hex(random_bytes(32));
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function confirm(): void
    {
        $this->status      = SubscriberStatus::Active;
        $this->confirmedAt = new \DateTimeImmutable();
        $this->updatedAt   = new \DateTimeImmutable();
    }

    public function unsubscribe(): void
    {
        $this->status          = SubscriberStatus::Unsubscribed;
        $this->unsubscribedAt  = new \DateTimeImmutable();
        $this->updatedAt       = new \DateTimeImmutable();
    }

    public function resubscribe(): void
    {
        $this->token           = bin2hex(random_bytes(32));
        $this->status          = SubscriberStatus::Pending;
        $this->confirmedAt     = null;
        $this->unsubscribedAt  = null;
        $this->updatedAt       = new \DateTimeImmutable();
    }

    public function getId(): int { return $this->id; }
    public function getSite(): Site { return $this->site; }
    public function setSite(Site $site): static { $this->site = $site; return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }
    public function getStatus(): SubscriberStatus { return $this->status; }
    public function setStatus(SubscriberStatus $status): static { $this->status = $status; return $this; }
    public function getToken(): string { return $this->token; }
    public function getConfirmedAt(): ?\DateTimeImmutable { return $this->confirmedAt; }
    public function getUnsubscribedAt(): ?\DateTimeImmutable { return $this->unsubscribedAt; }
    public function getSource(): string { return $this->source; }
    public function setSource(string $source): static { $this->source = $source; return $this; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ip): static { $this->ipAddress = $ip; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $ua): static { $this->userAgent = $ua; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
