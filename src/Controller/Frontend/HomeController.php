<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Service\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        $site = $this->siteContext->getSite();

        return $this->render('frontend/home.html.twig', [
            'site' => $site,
            'articles' => $this->articleRepository->findPublished(limit: 10),
            'categories' => $this->categoryRepository->findActive(),
        ]);
    }
}
