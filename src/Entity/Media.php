<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MediaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'media')]
#[ORM\Index(columns: ['site_id'], name: 'idx_site')]
class Media implements SiteAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $fileSize;

    #[ORM\Column(length: 500)]
    private string $path;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $altText = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->originalFilename;
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

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): static
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function setAltText(?string $altText): static
    {
        $this->altText = $altText;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPublicUrl(): string
    {
        return '/uploads/' . $this->path;
    }

    /**
     * Path to the thumb variant (400×225). Falls back to main path for SVG or legacy files.
     */
    public function getThumbPath(): string
    {
        if (str_ends_with($this->path, '.svg')) {
            return $this->path;
        }

        return substr($this->path, 0, -5) . '-sm.webp';
    }

    /**
     * Path to the medium variant (800×450). Falls back to main path for SVG or legacy files.
     */
    public function getMediumPath(): string
    {
        if (str_ends_with($this->path, '.svg')) {
            return $this->path;
        }

        return substr($this->path, 0, -5) . '-md.webp';
    }

    /** /uploads/ URL for the thumb variant. */
    public function getThumbUrl(): string
    {
        return '/uploads/' . $this->getThumbPath();
    }

    /** /uploads/ URL for the medium variant. */
    public function getMediumUrl(): string
    {
        return '/uploads/' . $this->getMediumPath();
    }

    /**
     * Returns a ready-to-use `srcset` attribute value.
     * SVGs return a single entry (vector, no variants needed).
     */
    public function getSrcset(): string
    {
        if (str_ends_with($this->path, '.svg')) {
            return $this->getPublicUrl();
        }

        return sprintf(
            '%s 1200w, %s 800w, %s 400w',
            $this->getPublicUrl(),
            $this->getMediumUrl(),
            $this->getThumbUrl(),
        );
    }
}
