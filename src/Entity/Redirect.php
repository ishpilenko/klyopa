<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RedirectRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RedirectRepository::class)]
#[ORM\Table(name: 'redirects')]
#[ORM\Index(columns: ['site_id', 'source_path'], name: 'idx_site_source')]
class Redirect implements SiteAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(length: 2048)]
    private string $sourcePath;

    #[ORM\Column(length: 2048)]
    private string $targetPath;

    #[ORM\Column(type: 'smallint', options: ['default' => 301])]
    private int $statusCode = 301;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'default' => 0])]
    private int $hits = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

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

    public function getSourcePath(): string
    {
        return $this->sourcePath;
    }

    public function setSourcePath(string $sourcePath): static
    {
        $this->sourcePath = $sourcePath;

        return $this;
    }

    public function getTargetPath(): string
    {
        return $this->targetPath;
    }

    public function setTargetPath(string $targetPath): static
    {
        $this->targetPath = $targetPath;

        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getHits(): int
    {
        return $this->hits;
    }

    public function incrementHits(): static
    {
        ++$this->hits;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
