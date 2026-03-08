<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\CoinPageRepository;
use App\Repository\GlossaryTermRepository;
use App\Repository\ToolRepository;
use App\Service\CoinGecko\CoinGeckoClient;
use App\Service\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class SitemapController extends AbstractController
{
    private const ARTICLES_PER_PAGE = 1000;

    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly ToolRepository $toolRepository,
        private readonly CoinPageRepository $coinPageRepository,
        private readonly GlossaryTermRepository $glossaryTermRepository,
        private readonly CoinGeckoClient $coinGecko,
    ) {
    }

    /** Sitemap index listing all sub-sitemaps */
    #[Route('/sitemap.xml', name: 'app_sitemap_index', methods: ['GET'])]
    public function index(): Response
    {
        $site = $this->siteContext->getSite();
        $baseUrl = 'https://' . $site->getDomain();
        $totalArticles = $this->articleRepository->countPublished();
        $totalPages = max(1, (int) ceil($totalArticles / self::ARTICLES_PER_PAGE));

        return $this->xmlResponse('sitemap/index.xml.twig', [
            'base_url' => $baseUrl,
            'total_article_pages' => $totalPages,
            'last_modified' => new \DateTimeImmutable(),
        ]);
    }

    /** Paginated article sitemap */
    #[Route('/sitemap-articles-{page}.xml', name: 'app_sitemap_articles', methods: ['GET'],
        requirements: ['page' => '\d+']
    )]
    public function articles(int $page): Response
    {
        if ($page < 1) {
            throw new NotFoundHttpException();
        }

        $site = $this->siteContext->getSite();
        $baseUrl = 'https://' . $site->getDomain();
        $offset = ($page - 1) * self::ARTICLES_PER_PAGE;

        $articles = $this->articleRepository->findForSitemap($offset, self::ARTICLES_PER_PAGE);

        if (empty($articles) && $page > 1) {
            throw new NotFoundHttpException();
        }

        return $this->xmlResponse('sitemap/articles.xml.twig', [
            'base_url' => $baseUrl,
            'articles' => $articles,
        ]);
    }

    /** Category sitemap */
    #[Route('/sitemap-categories.xml', name: 'app_sitemap_categories', methods: ['GET'])]
    public function categories(): Response
    {
        $site = $this->siteContext->getSite();
        $baseUrl = 'https://' . $site->getDomain();
        $categories = $this->categoryRepository->findActive();

        return $this->xmlResponse('sitemap/categories.xml.twig', [
            'base_url' => $baseUrl,
            'categories' => $categories,
        ]);
    }

    /** Tools sitemap */
    #[Route('/sitemap-tools.xml', name: 'app_sitemap_tools', methods: ['GET'])]
    public function tools(): Response
    {
        $site = $this->siteContext->getSite();
        $baseUrl = 'https://' . $site->getDomain();
        $tools = $this->toolRepository->findActive();

        return $this->xmlResponse('sitemap/tools.xml.twig', [
            'base_url' => $baseUrl,
            'tools' => $tools,
        ]);
    }

    /** Price pages sitemap */
    #[Route('/sitemap-prices.xml', name: 'app_sitemap_prices', methods: ['GET'])]
    public function prices(): Response
    {
        $site    = $this->siteContext->getSite();
        $baseUrl = 'https://' . $site->getDomain();
        $coins   = $this->coinPageRepository->findForSitemap();

        return $this->xmlResponse('sitemap/prices.xml.twig', [
            'base_url' => $baseUrl,
            'coins'    => $coins,
        ]);
    }

    /** Glossary sitemap */
    #[Route('/sitemap-glossary.xml', name: 'app_sitemap_glossary', methods: ['GET'])]
    public function glossary(): Response
    {
        $site    = $this->siteContext->getSite();
        $baseUrl = 'https://' . $site->getDomain();
        $terms   = $this->glossaryTermRepository->findForSitemap();

        return $this->xmlResponse('sitemap/glossary.xml.twig', [
            'base_url' => $baseUrl,
            'terms'    => $terms,
        ]);
    }

    /** Converter pages sitemap (dynamic, from CoinGecko top coins) */
    #[Route('/sitemap-converter.xml', name: 'app_sitemap_converter', methods: ['GET'])]
    public function converter(): Response
    {
        $site    = $this->siteContext->getSite();
        $baseUrl = 'https://' . $site->getDomain();
        $topCoins = $this->coinGecko->getTopCoins('usd', 100);
        $fiats    = ['usd', 'eur', 'gbp', 'jpy', 'aud', 'cad', 'chf', 'cny'];

        return $this->xmlResponse('sitemap/converter.xml.twig', [
            'base_url'  => $baseUrl,
            'top_coins' => $topCoins,
            'fiats'     => $fiats,
        ]);
    }

    private function xmlResponse(string $template, array $context): Response
    {
        $content = $this->renderView($template, $context);

        return new Response($content, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
