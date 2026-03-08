<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SiteVertical;
use App\Repository\SiteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SiteRepository::class)]
#[ORM\Table(name: 'sites')]
#[ORM\Index(columns: ['domain'], name: 'idx_domain')]
#[ORM\Index(columns: ['vertical'], name: 'idx_vertical')]
class Site
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $id;

    #[ORM\Column(length: 255, unique: true)]
    private string $domain;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', enumType: SiteVertical::class)]
    private SiteVertical $vertical;

    #[ORM\Column(length: 50, options: ['default' => 'default'])]
    private string $theme = 'default';

    #[ORM\Column(length: 10, options: ['default' => 'en'])]
    private string $locale = 'en';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $defaultMetaTitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $defaultMetaDescription = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $analyticsId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $searchConsoleId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $settings = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(targetEntity: Category::class, mappedBy: 'site', cascade: ['persist'])]
    private Collection $categories;

    #[ORM\OneToMany(targetEntity: Article::class, mappedBy: 'site', cascade: ['persist'])]
    private Collection $articles;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name . ' (' . $this->domain . ')';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): static
    {
        $this->domain = $domain;

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

    public function getVertical(): SiteVertical
    {
        return $this->vertical;
    }

    public function setVertical(SiteVertical $vertical): static
    {
        $this->vertical = $vertical;

        return $this;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getDefaultMetaTitle(): ?string
    {
        return $this->defaultMetaTitle;
    }

    public function setDefaultMetaTitle(?string $defaultMetaTitle): static
    {
        $this->defaultMetaTitle = $defaultMetaTitle;

        return $this;
    }

    public function getDefaultMetaDescription(): ?string
    {
        return $this->defaultMetaDescription;
    }

    public function setDefaultMetaDescription(?string $defaultMetaDescription): static
    {
        $this->defaultMetaDescription = $defaultMetaDescription;

        return $this;
    }

    public function getAnalyticsId(): ?string
    {
        return $this->analyticsId;
    }

    public function setAnalyticsId(?string $analyticsId): static
    {
        $this->analyticsId = $analyticsId;

        return $this;
    }

    public function getSearchConsoleId(): ?string
    {
        return $this->searchConsoleId;
    }

    public function setSearchConsoleId(?string $searchConsoleId): static
    {
        $this->searchConsoleId = $searchConsoleId;

        return $this;
    }

    public function getSettings(): ?array
    {
        return $this->settings;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function setSettings(?array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    /** Virtual property for EasyAdmin CodeEditorField (needs string, not array) */
    public function getSettingsJson(): ?string
    {
        return $this->settings !== null
            ? json_encode($this->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : null;
    }

    public function setSettingsJson(?string $json): static
    {
        if ($json === null || trim($json) === '') {
            $this->settings = null;
        } else {
            $decoded = json_decode($json, true);
            $this->settings = (JSON_ERROR_NONE === json_last_error()) ? $decoded : $this->settings;
        }

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

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /** @return Collection<int, Category> */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    /** @return Collection<int, Article> */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
