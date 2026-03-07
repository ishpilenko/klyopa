<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\CategoryRepository;
use App\Service\SiteContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/categories', name: 'api_category_')]
class CategoryApiController extends AbstractApiController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    /** GET /api/v1/categories */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $categories = $this->categoryRepository->findActive();

        return $this->success(array_map(fn($cat) => [
            'id' => $cat->getId(),
            'site_id' => $cat->getSite()->getId(),
            'name' => $cat->getName(),
            'slug' => $cat->getSlug(),
            'description' => $cat->getDescription(),
            'parent_id' => $cat->getParent()?->getId(),
            'sort_order' => $cat->getSortOrder(),
        ], $categories));
    }
}
