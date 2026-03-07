<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ToolType;
use App\Repository\ToolRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ToolRepository::class)]
#[ORM\Table(name: 'tools')]
#[ORM\UniqueConstraint(name: 'uniq_site_slug', columns: ['site_id', 'slug'])]
#[ORM\HasLifecycleCallbacks]
class Tool implements SiteAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(type: 'string', enumType: ToolType::class)]
    private ToolType $type;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'json')]
    private array $config = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(length: 320, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(length: 50, nullable: true, options: ['default' => 'SoftwareApplication'])]
    private ?string $schemaType = 'SoftwareApplication';

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
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

    public function getType(): ToolType
    {
        return $this->type;
    }

    public function setType(ToolType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): static
    {
        $this->config = $config;

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

    public function getSchemaType(): ?string
    {
        return $this->schemaType;
    }

    public function setSchemaType(?string $schemaType): static
    {
        $this->schemaType = $schemaType;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
