<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ArticleSchemaType;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'articles')]
#[ORM\UniqueConstraint(name: 'uniq_site_slug', columns: ['site_id', 'slug'])]
#[ORM\Index(columns: ['site_id', 'status', 'published_at'], name: 'idx_site_status_published')]
#[ORM\Index(columns: ['site_id', 'category_id'], name: 'idx_site_category')]
#[ORM\HasLifecycleCallbacks]
class Article implements SiteAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Site::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(name: 'category_id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column(length: 500)]
    private string $title;

    #[ORM\Column(length: 500)]
    private string $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $excerpt = null;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'string', enumType: ArticleStatus::class, options: ['default' => 'draft'])]
    private ArticleStatus $status = ArticleStatus::Draft;

    #[ORM\Column(type: 'string', enumType: ArticleSchemaType::class, options: ['default' => 'Article'])]
    private ArticleSchemaType $schemaType = ArticleSchemaType::Article;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(length: 320, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(name: 'featured_image_id', nullable: true, onDelete: 'SET NULL')]
    private ?Media $featuredImage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $authorName = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isAiGenerated = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isEvergreen = false;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'default' => 0])]
    private int $wordCount = 0;

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true, 'default' => 0])]
    private int $readingTimeMinutes = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $contentUpdatedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'articles', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'article_tags')]
    private Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function setExcerpt(?string $excerpt): static
    {
        $this->excerpt = $excerpt;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getStatus(): ArticleStatus
    {
        return $this->status;
    }

    public function setStatus(ArticleStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSchemaType(): ArticleSchemaType
    {
        return $this->schemaType;
    }

    public function setSchemaType(ArticleSchemaType $schemaType): static
    {
        $this->schemaType = $schemaType;

        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): static
    {
        $this->metaTitle = $metaTitle;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    public function getFeaturedImage(): ?Media
    {
        return $this->featuredImage;
    }

    public function setFeaturedImage(?Media $featuredImage): static
    {
        $this->featuredImage = $featuredImage;

        return $this;
    }

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(?string $authorName): static
    {
        $this->authorName = $authorName;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function isAiGenerated(): bool
    {
        return $this->isAiGenerated;
    }

    public function setIsAiGenerated(bool $isAiGenerated): static
    {
        $this->isAiGenerated = $isAiGenerated;

        return $this;
    }

    public function isEvergreen(): bool
    {
        return $this->isEvergreen;
    }

    public function setIsEvergreen(bool $isEvergreen): static
    {
        $this->isEvergreen = $isEvergreen;

        return $this;
    }

    public function getWordCount(): int
    {
        return $this->wordCount;
    }

    public function setWordCount(int $wordCount): static
    {
        $this->wordCount = $wordCount;

        return $this;
    }

    public function getReadingTimeMinutes(): int
    {
        return $this->readingTimeMinutes;
    }

    public function setReadingTimeMinutes(int $readingTimeMinutes): static
    {
        $this->readingTimeMinutes = $readingTimeMinutes;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getContentUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->contentUpdatedAt;
    }

    public function setContentUpdatedAt(?\DateTimeImmutable $contentUpdatedAt): static
    {
        $this->contentUpdatedAt = $contentUpdatedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function publish(): static
    {
        $this->status = ArticleStatus::Published;
        $this->publishedAt ??= new \DateTimeImmutable();

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->status === ArticleStatus::Published;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
