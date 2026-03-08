<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\SiteAwareInterface;
use App\Repository\GlossaryTermRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GlossaryTermRepository::class)]
#[ORM\Table(name: 'glossary_terms')]
#[ORM\UniqueConstraint(name: 'uniq_site_slug', columns: ['site_id', 'slug'])]
#[ORM\Index(columns: ['site_id', 'first_letter', 'status'], name: 'idx_site_letter')]
#[ORM\HasLifecycleCallbacks]
class GlossaryTerm implements SiteAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(type: 'string', length: 255)]
    private string $term;

    #[ORM\Column(type: 'string', length: 255)]
    private string $slug;

    #[ORM\Column(type: 'text')]
    private string $shortDefinition;

    #[ORM\Column(type: 'text')]
    private string $fullContent;

    /** @var array<int, array{question: string, answer: string}>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $faqJson = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(type: 'string', length: 320, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(type: 'string', length: 1)]
    private string $firstLetter;

    /** @var string[]|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $relatedTermSlugs = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'published';

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
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ── SiteAwareInterface ───────────────────────────────────────────────────

    public function getSite(): Site { return $this->site; }
    public function setSite(Site $site): static { $this->site = $site; return $this; }

    // ── Getters / Setters ────────────────────────────────────────────────────

    public function getId(): int { return $this->id; }

    public function getTerm(): string { return $this->term; }
    public function setTerm(string $term): static { $this->term = $term; return $this; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getShortDefinition(): string { return $this->shortDefinition; }
    public function setShortDefinition(string $shortDefinition): static
    {
        $this->shortDefinition = $shortDefinition;
        return $this;
    }

    public function getFullContent(): string { return $this->fullContent; }
    public function setFullContent(string $fullContent): static
    {
        $this->fullContent = $fullContent;
        return $this;
    }

    /** @return array<int, array{question: string, answer: string}>|null */
    public function getFaqJson(): ?array { return $this->faqJson; }

    /** @param array<int, array{question: string, answer: string}>|null $faqJson */
    public function setFaqJson(?array $faqJson): static { $this->faqJson = $faqJson; return $this; }

    public function getMetaTitle(): ?string { return $this->metaTitle; }
    public function setMetaTitle(?string $metaTitle): static { $this->metaTitle = $metaTitle; return $this; }

    public function getMetaDescription(): ?string { return $this->metaDescription; }
    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;
        return $this;
    }

    public function getFirstLetter(): string { return $this->firstLetter; }
    public function setFirstLetter(string $firstLetter): static
    {
        $this->firstLetter = strtoupper($firstLetter[0]);
        return $this;
    }

    /** @return string[]|null */
    public function getRelatedTermSlugs(): ?array { return $this->relatedTermSlugs; }

    /** @param string[]|null $slugs */
    public function setRelatedTermSlugs(?array $slugs): static
    {
        $this->relatedTermSlugs = $slugs;
        return $this;
    }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function isPublished(): bool { return $this->status === 'published'; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
