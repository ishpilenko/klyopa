<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\MediaManager;
use App\Service\SiteContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/media', name: 'api_media_')]
class MediaApiController extends AbstractApiController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly MediaManager $mediaManager,
    ) {
    }

    /** POST /api/v1/media (multipart/form-data, field: file) */
    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file) {
            return $this->error("Field 'file' is required.");
        }

        try {
            $media = $this->mediaManager->upload($file, $this->siteContext->getSite());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'id' => $media->getId(),
            'filename' => $media->getFilename(),
            'path' => $media->getPath(),
            'mime_type' => $media->getMimeType(),
            'file_size' => $media->getFileSize(),
            'alt_text' => $media->getAltText(),
        ], 201);
    }
}
