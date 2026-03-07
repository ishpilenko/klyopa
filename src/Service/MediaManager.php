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

    private const THUMB_WIDTH = 800;
    private const THUMB_HEIGHT = 600;

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
        $safeFilename = preg_replace('/[^a-z0-9-]/', '-', strtolower($originalFilename));
        $newFilename = $safeFilename . '-' . uniqid() . '.webp';

        $mimeType = $file->getMimeType() ?? 'image/jpeg';

        // Convert to WebP if it's an image (not SVG)
        if ('image/svg+xml' !== $mimeType) {
            $this->convertToWebP($file->getPathname(), $siteUploadDir . '/' . $newFilename);
        } else {
            $file->move($siteUploadDir, $newFilename = $safeFilename . '-' . uniqid() . '.svg');
        }

        $relativePath = $site->getId() . '/' . $newFilename;
        $fullPath = $siteUploadDir . '/' . $newFilename;

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
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        $this->em->remove($media);
    }

    private function validate(UploadedFile $file): void
    {
        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf('File type "%s" is not allowed. Allowed: %s', $file->getMimeType(), implode(', ', self::ALLOWED_MIME_TYPES))
            );
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(
                sprintf('File size %s exceeds the maximum allowed size of %s.', $this->formatBytes($file->getSize()), $this->formatBytes(self::MAX_FILE_SIZE))
            );
        }
    }

    private function convertToWebP(string $sourcePath, string $destPath): void
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
            default => throw new \RuntimeException('Unsupported image type.'),
        };

        if (false === $image) {
            throw new \RuntimeException('Cannot create image from file.');
        }

        // Resize if too large
        $origWidth = imagesx($image);
        $origHeight = imagesy($image);

        if ($origWidth > self::THUMB_WIDTH || $origHeight > self::THUMB_HEIGHT) {
            $ratio = min(self::THUMB_WIDTH / $origWidth, self::THUMB_HEIGHT / $origHeight);
            $newWidth = (int) ($origWidth * $ratio);
            $newHeight = (int) ($origHeight * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            // Preserve transparency for PNG
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            imagedestroy($image);
            $image = $resized;
        }

        imagewebp($image, $destPath, 85);
        imagedestroy($image);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        return round($bytes / 1024, 2) . ' KB';
    }
}
