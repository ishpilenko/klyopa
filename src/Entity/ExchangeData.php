<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\SiteAwareInterface;
use App\Repository\ExchangeDataRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExchangeDataRepository::class)]
#[ORM\Table(name: 'exchange_data')]
#[ORM\UniqueConstraint(name: 'uniq_site_slug', columns: ['site_id', 'slug'])]
#[ORM\HasLifecycleCallbacks]
class ExchangeData implements SiteAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255)]
    private string $slug;

    #[ORM\Column(type: 'decimal', precision: 3, scale: 1, nullable: true)]
    private ?string $rating = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $foundedYear = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $headquarters = null;

    #[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
    private ?int $supportedCoins = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 4, nullable: true)]
    private ?string $tradingFeeMaker = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 4, nullable: true)]
    private ?string $tradingFeeTaker = null;

    #[ORM\Column(type: 'boolean')]
    private bool $hasMobileApp = true;

    #[ORM\Column(type: 'boolean')]
    private bool $isRegulated = false;

    #[ORM\Column(type: 'boolean')]
    private bool $kycRequired = true;

    #[ORM\Column(type: 'string', length: 2048, nullable: true)]
    private ?string $affiliateUrl = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'review_article_id', nullable: true, onDelete: 'SET NULL')]
    private ?Article $reviewArticle = null;

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

    public function getSite(): Site { return $this->site; }
    public function setSite(Site $site): static { $this->site = $site; return $this; }

    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }
    public function getRating(): ?float { return $this->rating !== null ? (float) $this->rating : null; }
    public function setRating(?float $rating): static { $this->rating = $rating !== null ? (string) $rating : null; return $this; }
    public function getFoundedYear(): ?int { return $this->foundedYear; }
    public function setFoundedYear(?int $foundedYear): static { $this->foundedYear = $foundedYear; return $this; }
    public function getHeadquarters(): ?string { return $this->headquarters; }
    public function setHeadquarters(?string $headquarters): static { $this->headquarters = $headquarters; return $this; }
    public function getSupportedCoins(): ?int { return $this->supportedCoins; }
    public function setSupportedCoins(?int $supportedCoins): static { $this->supportedCoins = $supportedCoins; return $this; }
    public function getTradingFeeMaker(): ?float { return $this->tradingFeeMaker !== null ? (float) $this->tradingFeeMaker : null; }
    public function setTradingFeeMaker(?float $fee): static { $this->tradingFeeMaker = $fee !== null ? (string) $fee : null; return $this; }
    public function getTradingFeeTaker(): ?float { return $this->tradingFeeTaker !== null ? (float) $this->tradingFeeTaker : null; }
    public function setTradingFeeTaker(?float $fee): static { $this->tradingFeeTaker = $fee !== null ? (string) $fee : null; return $this; }
    public function hasMobileApp(): bool { return $this->hasMobileApp; }
    public function setHasMobileApp(bool $hasMobileApp): static { $this->hasMobileApp = $hasMobileApp; return $this; }
    public function isRegulated(): bool { return $this->isRegulated; }
    public function setIsRegulated(bool $isRegulated): static { $this->isRegulated = $isRegulated; return $this; }
    public function isKycRequired(): bool { return $this->kycRequired; }
    public function setKycRequired(bool $kycRequired): static { $this->kycRequired = $kycRequired; return $this; }
    public function getAffiliateUrl(): ?string { return $this->affiliateUrl; }
    public function setAffiliateUrl(?string $affiliateUrl): static { $this->affiliateUrl = $affiliateUrl; return $this; }
    public function getReviewArticle(): ?Article { return $this->reviewArticle; }
    public function setReviewArticle(?Article $reviewArticle): static { $this->reviewArticle = $reviewArticle; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
