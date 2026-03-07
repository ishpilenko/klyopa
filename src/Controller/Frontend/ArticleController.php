<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Service\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ArticleController extends AbstractController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    #[Route('/{categorySlug}/{articleSlug}', name: 'app_article_show', methods: ['GET'],
        requirements: ['categorySlug' => '[a-z0-9-]+', 'articleSlug' => '[a-z0-9-]+']
    )]
    public function show(string $categorySlug, string $articleSlug): Response
    {
        $site = $this->siteContext->getSite();

        $category = $this->categoryRepository->findBySlug($categorySlug);
        if (null === $category) {
            throw new NotFoundHttpException('Category not found.');
        }

        $article = $this->articleRepository->findBySlug($articleSlug);
        if (null === $article || $article->getCategory()?->getSlug() !== $categorySlug) {
            throw new NotFoundHttpException('Article not found.');
        }

        $relatedArticles = $this->articleRepository->findByCategory($category, 4);

        return $this->render('frontend/article/show.html.twig', [
            'site' => $site,
            'article' => $article,
            'category' => $category,
            'categories' => $this->categoryRepository->findActive(),
            'related_articles' => array_filter($relatedArticles, fn($a) => $a->getId() !== $article->getId()),
            'meta_title' => $article->getMetaTitle() ?? $article->getTitle(),
            'meta_description' => $article->getMetaDescription() ?? $article->getExcerpt(),
        ]);
    }
}
