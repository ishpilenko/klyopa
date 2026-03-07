<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Repository\CategoryRepository;
use App\Repository\ToolRepository;
use App\Service\BreadcrumbService;
use App\Service\SeoManager;
use App\Service\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ToolController extends AbstractController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly ToolRepository $toolRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly SeoManager $seoManager,
        private readonly BreadcrumbService $breadcrumbService,
    ) {
    }

    #[Route('/tools/{slug}', name: 'app_tool_show', methods: ['GET'],
        requirements: ['slug' => '[a-z0-9-]+'],
        priority: 10,
    )]
    public function show(string $slug): Response
    {
        $site = $this->siteContext->getSite();

        $tool = $this->toolRepository->findBySlug($slug);
        if (!$tool) {
            throw new NotFoundHttpException('Tool not found.');
        }

        $meta = $this->seoManager->forTool($tool, $site);

        return $this->render('frontend/tool/show.html.twig', [
            'site' => $site,
            'tool' => $tool,
            'categories' => $this->categoryRepository->findActive(),
            'breadcrumbs' => $this->breadcrumbService->forTool($tool),
            ...$meta,
        ]);
    }

    #[Route('/tools/', name: 'app_tools_index', methods: ['GET'], priority: 10)]
    public function index(): Response
    {
        $site = $this->siteContext->getSite();
        $tools = $this->toolRepository->findActive();
        $meta = $this->seoManager->forSite($site);

        return $this->render('frontend/tool/index.html.twig', [
            'site' => $site,
            'tools' => $tools,
            'categories' => $this->categoryRepository->findActive(),
            'breadcrumbs' => [
                ['label' => 'Home', 'url' => '/'],
                ['label' => 'Tools', 'url' => null],
            ],
            ...$meta,
        ]);
    }
}
