<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\SiteRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/sites', name: 'api_site_')]
class SiteApiController extends AbstractApiController
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
    ) {
    }

    /** GET /api/v1/sites */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $sites = $this->siteRepository->findBy(['isActive' => true], ['name' => 'ASC']);

        return $this->success(array_map(fn($site) => [
            'id' => $site->getId(),
            'domain' => $site->getDomain(),
            'name' => $site->getName(),
            'vertical' => $site->getVertical()->value,
            'theme' => $site->getTheme(),
            'locale' => $site->getLocale(),
            'settings' => $site->getSettings(),
        ], $sites));
    }
}
