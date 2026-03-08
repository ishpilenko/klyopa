<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\SiteAwareInterface;
use App\Enum\PartnerType;
use App\Repository\AffiliateLinkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AffiliateLinkRepository::class)]
#[ORM\Table(name: 'affiliate_links')]
#[ORM\UniqueConstraint(name: 'uniq_site_partner', columns: ['site_id', 'partner'])]
#[ORM\HasLifecycleCallbacks]
class AffiliateLink implements SiteAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(type: 'string', length: 100)]
    private string $partner;

    #[ORM\Column(type: 'string', length: 20, enumType: PartnerType::class)]
    private PartnerType $partnerType;

    #[ORM\Column(type: 'string', length: 2048)]
    private string $baseUrl;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $utmSource = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $utmMedium = 'affiliate';

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $utmCampaign = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $displayName;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $clicks = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getSite(): Site { return $this->site; }
    public function setSite(Site $site): static { $this->site = $site; return $this; }

    public function getId(): int { return $this->id; }
    public function getPartner(): string { return $this->partner; }
    public function setPartner(string $partner): static { $this->partner = $partner; return $this; }
    public function getPartnerType(): PartnerType { return $this->partnerType; }
    public function setPartnerType(PartnerType $partnerType): static { $this->partnerType = $partnerType; return $this; }
    public function getBaseUrl(): string { return $this->baseUrl; }
    public function setBaseUrl(string $baseUrl): static { $this->baseUrl = $baseUrl; return $this; }
    public function getUtmSource(): ?string { return $this->utmSource; }
    public function setUtmSource(?string $utmSource): static { $this->utmSource = $utmSource; return $this; }
    public function getUtmMedium(): ?string { return $this->utmMedium; }
    public function setUtmMedium(?string $utmMedium): static { $this->utmMedium = $utmMedium; return $this; }
    public function getUtmCampaign(): ?string { return $this->utmCampaign; }
    public function setUtmCampaign(?string $utmCampaign): static { $this->utmCampaign = $utmCampaign; return $this; }
    public function getDisplayName(): string { return $this->displayName; }
    public function setDisplayName(string $displayName): static { $this->displayName = $displayName; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getClicks(): int { return $this->clicks; }
    public function incrementClicks(): static { $this->clicks++; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** Build the full affiliate URL with UTM params. */
    public function getFullUrl(): string
    {
        $params = array_filter([
            'utm_source'   => $this->utmSource,
            'utm_medium'   => $this->utmMedium,
            'utm_campaign' => $this->utmCampaign,
        ]);

        if (empty($params)) {
            return $this->baseUrl;
        }

        $sep = str_contains($this->baseUrl, '?') ? '&' : '?';
        return $this->baseUrl . $sep . http_build_query($params);
    }
}
