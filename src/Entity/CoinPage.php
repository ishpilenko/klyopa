<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CoinPageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CoinPageRepository::class)]
#[ORM\Table(name: 'coin_pages')]
#[ORM\UniqueConstraint(name: 'uniq_site_slug', columns: ['site_id', 'slug'])]
#[ORM\UniqueConstraint(name: 'uniq_site_coingecko', columns: ['site_id', 'coin_gecko_id'])]
#[ORM\HasLifecycleCallbacks]
class CoinPage implements SiteAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(length: 100)]
    private string $coinGeckoId;   // "bitcoin"

    #[ORM\Column(length: 20)]
    private string $symbol;         // "BTC"

    #[ORM\Column(length: 255)]
    private string $name;           // "Bitcoin"

    #[ORM\Column(length: 255)]
    private string $slug;           // "bitcoin"

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $faqJson = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageUrl = null;

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

    public function getId(): int { return $this->id; }

    public function getSite(): Site { return $this->site; }

    public function setSite(Site $site): static
    {
        $this->site = $site;
        return $this;
    }

    public function getCoinGeckoId(): string { return $this->coinGeckoId; }

    public function setCoinGeckoId(string $coinGeckoId): static
    {
        $this->coinGeckoId = $coinGeckoId;
        return $this;
    }

    public function getSymbol(): string { return $this->symbol; }

    public function setSymbol(string $symbol): static
    {
        $this->symbol = strtoupper($symbol);
        return $this;
    }

    public function getName(): string { return $this->name; }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): string { return $this->slug; }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getDescription(): ?string { return $this->description; }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getFaqJson(): ?array { return $this->faqJson; }

    public function setFaqJson(?array $faqJson): static
    {
        $this->faqJson = $faqJson;
        return $this;
    }

    public function getImageUrl(): ?string { return $this->imageUrl; }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function isActive(): bool { return $this->isActive; }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
