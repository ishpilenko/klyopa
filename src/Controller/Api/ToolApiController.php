<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\InternalLinker;
use App\Service\SiteContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/tools', name: 'api_tool_')]
class ToolApiController extends AbstractApiController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly InternalLinker $internalLinker,
    ) {
    }

    /**
     * POST /api/v1/tools/suggest-links
     * Body: {content: "html", exclude_article_id: 42, max_links: 5}
     */
    #[Route('/suggest-links', name: 'suggest_links', methods: ['POST'])]
    public function suggestLinks(Request $request): JsonResponse
    {
        $data = $this->decodeBody($request);
        if ($data === null) {
            return $this->error('Invalid JSON body.');
        }

        if (empty($data['content'])) {
            return $this->error("Field 'content' is required.");
        }

        $suggestions = $this->internalLinker->suggest(
            htmlContent: $data['content'],
            excludeArticleId: (int) ($data['exclude_article_id'] ?? 0),
            maxLinks: (int) ($data['max_links'] ?? 5),
        );

        return $this->success($suggestions);
    }
}
