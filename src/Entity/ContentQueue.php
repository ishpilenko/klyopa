<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContentQueueStatus;
use App\Repository\ContentQueueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContentQueueRepository::class)]
#[ORM\Table(name: 'content_queue')]
#[ORM\Index(columns: ['status', 'priority'], name: 'idx_status_priority')]
class ContentQueue implements SiteAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(length: 500)]
    private string $topic;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $keywords = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'target_category_id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $targetCategory = null;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'default' => 1500])]
    private int $targetWordCount = 1500;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $promptTemplate = null;

    #[ORM\Column(type: 'string', enumType: ContentQueueStatus::class, options: ['default' => 'pending'])]
    private ContentQueueStatus $status = ContentQueueStatus::Pending;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'result_article_id', nullable: true, onDelete: 'SET NULL')]
    private ?Article $resultArticle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $priority = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function setSite(Site $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function setTopic(string $topic): static
    {
        $this->topic = $topic;

        return $this;
    }

    public function getKeywords(): ?array
    {
        return $this->keywords;
    }

    public function setKeywords(?array $keywords): static
    {
        $this->keywords = $keywords;

        return $this;
    }

    public function getTargetCategory(): ?Category
    {
        return $this->targetCategory;
    }

    public function setTargetCategory(?Category $targetCategory): static
    {
        $this->targetCategory = $targetCategory;

        return $this;
    }

    public function getTargetWordCount(): int
    {
        return $this->targetWordCount;
    }

    public function setTargetWordCount(int $targetWordCount): static
    {
        $this->targetWordCount = $targetWordCount;

        return $this;
    }

    public function getPromptTemplate(): ?string
    {
        return $this->promptTemplate;
    }

    public function setPromptTemplate(?string $promptTemplate): static
    {
        $this->promptTemplate = $promptTemplate;

        return $this;
    }

    public function getStatus(): ContentQueueStatus
    {
        return $this->status;
    }

    public function setStatus(ContentQueueStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getResultArticle(): ?Article
    {
        return $this->resultArticle;
    }

    public function setResultArticle(?Article $resultArticle): static
    {
        $this->resultArticle = $resultArticle;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;

        return $this;
    }

    public function markAsProcessing(): static
    {
        $this->status = ContentQueueStatus::Processing;

        return $this;
    }

    public function markAsCompleted(Article $article): static
    {
        $this->status = ContentQueueStatus::Completed;
        $this->resultArticle = $article;
        $this->processedAt = new \DateTimeImmutable();

        return $this;
    }

    public function markAsFailed(string $errorMessage): static
    {
        $this->status = ContentQueueStatus::Failed;
        $this->errorMessage = $errorMessage;
        $this->processedAt = new \DateTimeImmutable();

        return $this;
    }
}
