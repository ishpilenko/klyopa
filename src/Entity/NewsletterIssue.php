<?php
declare(strict_types=1);
namespace App\Entity;

use App\Enum\IssueStatus;
use App\Repository\NewsletterIssueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NewsletterIssueRepository::class)]
#[ORM\Table(name: 'newsletter_issues')]
#[ORM\Index(name: 'idx_site_status', columns: ['site_id', 'status'])]
#[ORM\HasLifecycleCallbacks]
class NewsletterIssue implements SiteAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $previewText = null;

    #[ORM\Column(type: 'text', nullable: false)]
    private string $contentHtml = '';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $contentJson = null;

    #[ORM\Column(type: 'string', enumType: IssueStatus::class, options: ['default' => 'draft'])]
    private IssueStatus $status = IssueStatus::Draft;

    #[ORM\Column(length: 10, options: ['default' => 'ai'])]
    private string $generatedBy = 'ai';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'default' => 0])]
    private int $recipientsCount = 0;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'default' => 0])]
    private int $sentCount = 0;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'default' => 0])]
    private int $failedCount = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function getId(): int { return $this->id; }
    public function getSite(): Site { return $this->site; }
    public function setSite(Site $site): static { $this->site = $site; return $this; }
    public function getSubject(): string { return $this->subject; }
    public function setSubject(string $s): static { $this->subject = $s; return $this; }
    public function getPreviewText(): ?string { return $this->previewText; }
    public function setPreviewText(?string $t): static { $this->previewText = $t; return $this; }
    public function getContentHtml(): string { return $this->contentHtml; }
    public function setContentHtml(string $h): static { $this->contentHtml = $h; return $this; }
    public function getContentJson(): ?array { return $this->contentJson; }
    public function setContentJson(?array $j): static { $this->contentJson = $j; return $this; }
    public function getStatus(): IssueStatus { return $this->status; }
    public function setStatus(IssueStatus $s): static { $this->status = $s; return $this; }
    public function getGeneratedBy(): string { return $this->generatedBy; }
    public function setGeneratedBy(string $g): static { $this->generatedBy = $g; return $this; }
    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function setSentAt(?\DateTimeImmutable $t): static { $this->sentAt = $t; return $this; }
    public function getRecipientsCount(): int { return $this->recipientsCount; }
    public function setRecipientsCount(int $n): static { $this->recipientsCount = $n; return $this; }
    public function getSentCount(): int { return $this->sentCount; }
    public function setSentCount(int $n): static { $this->sentCount = $n; return $this; }
    public function getFailedCount(): int { return $this->failedCount; }
    public function setFailedCount(int $n): static { $this->failedCount = $n; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
