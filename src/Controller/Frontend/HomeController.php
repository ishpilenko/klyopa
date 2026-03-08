<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Enum\SiteVertical;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\GlossaryTermRepository;
use App\Service\CoinGecko\CoinGeckoClient;
use App\Service\FearGreedClient;
use App\Service\SeoManager;
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
        private readonly SeoManager $seoManager,
        private readonly CoinGeckoClient $coinGecko,
        private readonly FearGreedClient $fearGreedClient,
        private readonly GlossaryTermRepository $glossaryRepo,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        $site = $this->siteContext->getSite();
        $meta = $this->seoManager->forSite($site);

        $topCoins     = null;
        $fearGreed    = null;
        $glossaryCount = 0;

        if ($site->getVertical() === SiteVertical::Crypto) {
            $topCoins  = $this->coinGecko->getTopCoins('usd', 10);
            $fgHistory = $this->fearGreedClient->getIndex(1);
            $fearGreed = $fgHistory[0] ?? null;
            $glossaryCount = $this->glossaryRepo->countPublished();
        }

        return $this->render('frontend/home.html.twig', [
            'site'           => $site,
            'articles'       => $this->articleRepository->findPublished(limit: 10),
            'categories'     => $this->categoryRepository->findActive(),
            'top_coins'      => $topCoins,
            'fear_greed'     => $fearGreed,
            'glossary_count' => $glossaryCount,
            ...$meta,
        ]);
    }
}
