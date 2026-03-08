<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use App\Service\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class TagController extends AbstractController
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly TagRepository $tagRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    #[Route('/tag/{slug}', name: 'app_tag_show', methods: ['GET'],
        requirements: ['slug' => '[a-z0-9-]+']
    )]
    public function show(string $slug, Request $request): Response
    {
        $site = $this->siteContext->getSite();

        $tag = $this->tagRepository->findBySlug($slug);
        if (null === $tag) {
            throw new NotFoundHttpException('Tag not found.');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $total = $this->articleRepository->countByTag($tag);
        $articles = $this->articleRepository->findByTag($tag, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);

        return $this->render('frontend/tag/show.html.twig', [
            'site' => $site,
            'tag' => $tag,
            'categories' => $this->categoryRepository->findActive(),
            'articles' => $articles,
            'current_page' => $page,
            'total_pages' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            'meta_title' => '#' . $tag->getName() . ' — ' . $site->getName(),
            'meta_description' => 'Articles tagged with #' . $tag->getName() . ' on ' . $site->getName() . '.',
            'canonical' => $request->getUri(),
        ]);
    }
}
