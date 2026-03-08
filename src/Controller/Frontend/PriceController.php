<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Repository\CategoryRepository;
use App\Repository\CoinPageRepository;
use App\Service\CoinGecko\CoinGeckoClient;
use App\Service\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class PriceController extends AbstractController
{
    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly CoinGeckoClient $coinGecko,
        private readonly CoinPageRepository $coinPageRepository,
        private readonly CategoryRepository $categoryRepository,
    ) {}

    #[Route('/prices', name: 'price_index', methods: ['GET'], priority: 10)]
    public function index(): Response
    {
        $site  = $this->siteContext->getSite();
        $coins = $this->coinGecko->getTopCoins('usd', 100);

        return $this->render('frontend/price/index.html.twig', [
            'site'        => $site,
            'coins'       => $coins,
            'categories'  => $this->categoryRepository->findActive(),
            'breadcrumbs' => [
                ['label' => 'Home',           'url' => '/'],
                ['label' => 'Crypto Prices',  'url' => null],
            ],
            'meta_title'       => 'Cryptocurrency Prices Today — Live Market Data | ' . $site->getName(),
            'meta_description' => 'Live cryptocurrency prices, market caps, and 24h changes. Track Bitcoin, Ethereum, and 100+ altcoins.',
        ]);
    }

    #[Route('/price/{slug}', name: 'price_show', methods: ['GET'],
        requirements: ['slug' => '[a-z0-9-]+'],
        priority: 10,
    )]
    public function show(string $slug): Response
    {
        $site = $this->siteContext->getSite();

        // Try CoinPage from DB first, fall back to CoinGecko direct lookup
        $coinPage = $this->coinPageRepository->findBySlug($slug);
        $coinId   = $coinPage?->getCoinGeckoId() ?? $slug;

        $info = $this->coinGecko->getCoinInfo($coinId);

        // If DB has no record AND API also returned nothing → true 404
        if (!$info && !$coinPage) {
            throw new NotFoundHttpException('Coin not found.');
        }

        $chart7d = $info ? $this->coinGecko->getMarketChart($coinId, 'usd', 7) : null;

        // Prefer live API data; fall back to DB-stored values when API is unavailable
        $name   = $info['name']   ?? $coinPage?->getName()   ?? ucfirst($slug);
        $symbol = strtoupper($info['symbol'] ?? $coinPage?->getSymbol() ?? $slug);
        $price  = $info['market_data']['current_price']['usd'] ?? null;
        $change24h = $info['market_data']['price_change_percentage_24h'] ?? null;
        $change7d  = $info['market_data']['price_change_percentage_7d']  ?? null;
        $marketCap = $info['market_data']['market_cap']['usd']           ?? null;
        $volume24h = $info['market_data']['total_volume']['usd']         ?? null;
        $ath       = $info['market_data']['ath']['usd']                  ?? null;
        $athDate   = $info['market_data']['ath_date']['usd']             ?? null;
        $circSupply = $info['market_data']['circulating_supply']         ?? null;
        $maxSupply  = $info['market_data']['max_supply']                 ?? null;
        $rank       = $info['market_cap_rank']                           ?? null;
        $imageUrl   = $info['image']['large'] ?? $coinPage?->getImageUrl() ?? null;

        $priceFormatted = $price !== null ? '$' . number_format($price, $price >= 1 ? 2 : 8) : 'N/A';

        $title = sprintf('%s (%s) Price Today — Live %s/USD Chart | %s',
            $name, $symbol, $symbol, $site->getName());
        $desc  = sprintf('%s (%s) price is %s. Market cap $%s. See live chart and market data.',
            $name, $symbol, $priceFormatted,
            $marketCap ? number_format($marketCap / 1_000_000, 1) . 'M' : 'N/A');

        return $this->render('frontend/price/show.html.twig', [
            'site'        => $site,
            'categories'  => $this->categoryRepository->findActive(),
            'coin_page'   => $coinPage,
            'coin_id'     => $coinId,
            'name'        => $name,
            'symbol'      => $symbol,
            'price'       => $price,
            'change_24h'  => $change24h,
            'change_7d'   => $change7d,
            'market_cap'  => $marketCap,
            'volume_24h'  => $volume24h,
            'ath'         => $ath,
            'ath_date'    => $athDate,
            'circ_supply' => $circSupply,
            'max_supply'  => $maxSupply,
            'rank'        => $rank,
            'image_url'   => $imageUrl,
            'chart_7d'    => $chart7d,
            'breadcrumbs' => [
                ['label' => 'Home',         'url' => '/'],
                ['label' => 'Prices',       'url' => '/prices'],
                ['label' => $name . ' Price', 'url' => null],
            ],
            'meta_title'       => $title,
            'meta_description' => $desc,
        ]);
    }
}
