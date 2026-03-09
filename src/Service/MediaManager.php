<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Media;
use App\Entity\Site;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaManager
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    /**
     * Variant sizes: suffix => [maxWidth, maxHeight].
     * Empty suffix = large (stored as the main `path`).
     */
    private const SIZES = [
        ''   => [1200, 675],  // large  — stored in media.path
        'md' => [800,  450],  // medium
        'sm' => [400,  225],  // thumb
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $uploadsDir,
    ) {
    }

    public function upload(UploadedFile $file, Site $site, ?string $altText = null): Media
    {
        $this->validate($file);

        $siteUploadDir = $this->uploadsDir . '/' . $site->getId();
        if (!is_dir($siteUploadDir)) {
            mkdir($siteUploadDir, 0755, true);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename     = preg_replace('/[^a-z0-9-]/', '-', strtolower($originalFilename));
        $baseFilename     = $safeFilename . '-' . uniqid();
        $mimeType         = $file->getMimeType() ?? 'image/jpeg';

        if ('image/svg+xml' === $mimeType) {
            // SVGs stay as-is — no rasterisation
            $newFilename = $baseFilename . '.svg';
            $file->move($siteUploadDir, $newFilename);
        } else {
            // Generate large, medium and thumb variants
            foreach (self::SIZES as $suffix => [$maxW, $maxH]) {
                $variantFilename = $baseFilename . ($suffix ? '-' . $suffix : '') . '.webp';
                $this->resizeAndSave(
                    $file->getPathname(),
                    $siteUploadDir . '/' . $variantFilename,
                    $maxW,
                    $maxH,
                );
            }
            $newFilename = $baseFilename . '.webp'; // large = canonical path
        }

        $relativePath = $site->getId() . '/' . $newFilename;
        $fullPath     = $siteUploadDir . '/' . $newFilename;

        $media = (new Media())
            ->setSite($site)
            ->setFilename($newFilename)
            ->setOriginalFilename($file->getClientOriginalName())
            ->setMimeType('image/svg+xml' === $mimeType ? $mimeType : 'image/webp')
            ->setFileSize(file_exists($fullPath) ? (int) filesize($fullPath) : 0)
            ->setPath($relativePath)
            ->setAltText($altText);

        $this->em->persist($media);

        return $media;
    }

    public function delete(Media $media): void
    {
        $fullPath = $this->uploadsDir . '/' . $media->getPath();

        if (str_ends_with($fullPath, '.svg')) {
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        } else {
            // Remove all WebP variants
            $base = preg_replace('/\.webp$/', '', $fullPath);
            foreach (array_keys(self::SIZES) as $suffix) {
                $variantPath = $base . ($suffix ? '-' . $suffix : '') . '.webp';
                if (file_exists($variantPath)) {
                    unlink($variantPath);
                }
            }
        }

        $this->em->remove($media);
    }

    private function validate(UploadedFile $file): void
    {
        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'File type "%s" is not allowed. Allowed: %s',
                $file->getMimeType(),
                implode(', ', self::ALLOWED_MIME_TYPES),
            ));
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(sprintf(
                'File size %s exceeds the maximum allowed size of %s.',
                $this->formatBytes($file->getSize()),
                $this->formatBytes(self::MAX_FILE_SIZE),
            ));
        }
    }

    /**
     * Load an image, scale it to fit within maxW×maxH (never upscale), save as WebP.
     */
    private function resizeAndSave(string $sourcePath, string $destPath, int $maxW, int $maxH): void
    {
        $imageInfo = @getimagesize($sourcePath);
        if (false === $imageInfo) {
            throw new \RuntimeException('Cannot read image file.');
        }

        $image = match ($imageInfo[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF  => imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
            default        => throw new \RuntimeException('Unsupported image type.'),
        };

        if (false === $image) {
            throw new \RuntimeException('Cannot create image from file.');
        }

        $origW = imagesx($image);
        $origH = imagesy($image);

        // Scale down only — never upscale
        if ($origW > $maxW || $origH > $maxH) {
            $ratio  = min($maxW / $origW, $maxH / $origH);
            $newW   = (int) round($origW * $ratio);
            $newH   = (int) round($origH * $ratio);

            $canvas = imagecreatetruecolor($newW, $newH);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($image);
            $image = $canvas;
        }

        imagewebp($image, $destPath, 85);
        imagedestroy($image);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 2) . ' MB';
        }

        return round($bytes / 1024, 2) . ' KB';
    }
}
