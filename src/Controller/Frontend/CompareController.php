<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Service\CoinGecko\CoinGeckoClient;
use App\Service\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/compare', name: 'compare_', priority: 15)]
class CompareController extends AbstractController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly CoinGeckoClient $coinGecko,
    ) {
    }

    /** /compare — index listing all comparison articles */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $site       = $this->siteContext->getSite();
        $category   = $this->categoryRepository->findBySlug('comparisons');
        $articles   = $category
            ? $this->articleRepository->findByCategory($category)
            : [];

        return $this->render('frontend/compare/index.html.twig', [
            'site'            => $site,
            'categories'      => $this->categoryRepository->findActive(),
            'articles'        => $articles,
            'meta_title'      => 'Crypto Comparisons — Bitcoin vs Ethereum & More | ' . $site->getName(),
            'meta_description' => 'Detailed side-by-side comparisons of top cryptocurrencies. Bitcoin vs Ethereum, Solana vs Avalanche, CEX vs DEX and more.',
        ]);
    }

    /** /compare/{x}-vs-{y} — comparison article page */
    #[Route('/{slug}', name: 'show', methods: ['GET'],
        requirements: ['slug' => '[a-z0-9]+-vs-[a-z0-9-]+']
    )]
    public function show(string $slug): Response
    {
        $site    = $this->siteContext->getSite();
        $article = $this->articleRepository->findBySlug($slug);

        if (null === $article) {
            throw new NotFoundHttpException('Comparison not found.');
        }

        // Extract coin slugs from the compare slug (e.g. "bitcoin-vs-ethereum")
        $priceData = [];
        if (preg_match('/^([a-z0-9-]+)-vs-([a-z0-9-]+)$/', $slug, $m)) {
            $coinA = $m[1];
            $coinB = $m[2];
            $coins = $this->coinGecko->getTopCoins('usd', 250);
            foreach ($coins as $coin) {
                if ($coin['id'] === $coinA || $coin['id'] === $coinB) {
                    $priceData[$coin['id']] = $coin;
                }
            }
        }

        $relatedArticles = $this->articleRepository->findRelated($article->getId(), $article->getCategory(), 4);

        return $this->render('frontend/compare/show.html.twig', [
            'site'            => $site,
            'categories'      => $this->categoryRepository->findActive(),
            'article'         => $article,
            'price_data'      => $priceData,
            'related_articles' => $relatedArticles,
            'meta_title'      => $article->getMetaTitle() ?: $article->getTitle() . ' | ' . $site->getName(),
            'meta_description' => $article->getMetaDescription() ?: $article->getExcerpt(),
        ]);
    }
}
