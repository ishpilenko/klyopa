<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Service\SiteContext;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class CategoryController extends AbstractController
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly PaginatorInterface $paginator,
    ) {
    }

    #[Route('/{slug}/', name: 'app_category_listing', methods: ['GET'],
        requirements: ['slug' => '[a-z0-9-]+']
    )]
    public function listing(string $slug, Request $request): Response
    {
        $site = $this->siteContext->getSite();

        $category = $this->categoryRepository->findBySlug($slug);
        if (null === $category) {
            throw new NotFoundHttpException('Category not found.');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $totalArticles = $this->articleRepository->countPublished();
        $articles = $this->articleRepository->findByCategory($category, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);

        return $this->render('frontend/category/listing.html.twig', [
            'site' => $site,
            'category' => $category,
            'categories' => $this->categoryRepository->findActive(),
            'articles' => $articles,
            'current_page' => $page,
            'total_pages' => (int) ceil(count($articles) / self::PAGE_SIZE) ?: 1,
            'meta_title' => $category->getMetaTitle() ?? $category->getName() . ' — ' . $site->getName(),
            'meta_description' => $category->getMetaDescription() ?? $category->getDescription(),
        ]);
    }
}
