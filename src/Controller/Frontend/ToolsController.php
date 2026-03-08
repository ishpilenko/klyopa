<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Repository\CategoryRepository;
use App\Service\CoinGecko\CoinGeckoClient;
use App\Service\GasTrackerService;
use App\Service\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Dedicated routes for Phase-8 interactive calculators.
 * Priority 20 ensures these match before the generic /tools/{slug} route (priority 10).
 */
class ToolsController extends AbstractController
{
    /** Mining coin data (fallback constants; UpdateMiningDataCommand refreshes these in Redis). */
    private const MINING_COINS = [
        'bitcoin'  => [
            'id' => 'bitcoin', 'name' => 'Bitcoin', 'symbol' => 'BTC',
            'blockReward' => 3.125, 'blockTime' => 600,
            'networkHashrate' => 7.2e20, // ~720 EH/s in H/s
            'hashrateUnit' => 'TH/s', 'hashrateMultiplier' => 1e12,
            'defaultHashrate' => 100, 'defaultPower' => 3300,
        ],
        'litecoin' => [
            'id' => 'litecoin', 'name' => 'Litecoin', 'symbol' => 'LTC',
            'blockReward' => 6.25, 'blockTime' => 150,
            'networkHashrate' => 1.5e15, // ~1,500 TH/s in H/s
            'hashrateUnit' => 'GH/s', 'hashrateMultiplier' => 1e9,
            'defaultHashrate' => 1500, 'defaultPower' => 1500,
        ],
        'monero'   => [
            'id' => 'monero', 'name' => 'Monero', 'symbol' => 'XMR',
            'blockReward' => 0.6, 'blockTime' => 120,
            'networkHashrate' => 2.8e9, // ~2.8 GH/s in H/s
            'hashrateUnit' => 'KH/s', 'hashrateMultiplier' => 1e3,
            'defaultHashrate' => 15, 'defaultPower' => 150,
        ],
        'kaspa'    => [
            'id' => 'kaspa', 'name' => 'Kaspa', 'symbol' => 'KAS',
            'blockReward' => 50.0, 'blockTime' => 1,
            'networkHashrate' => 5e14, // ~500 TH/s in H/s
            'hashrateUnit' => 'TH/s', 'hashrateMultiplier' => 1e12,
            'defaultHashrate' => 10, 'defaultPower' => 1000,
        ],
    ];

    private const HARDWARE_PRESETS = [
        ['name' => 'Antminer S21 Pro',   'coin' => 'bitcoin',  'hashrate' => 234,  'hashrateUnit' => 'TH/s', 'power' => 3531],
        ['name' => 'Antminer S19j Pro',  'coin' => 'bitcoin',  'hashrate' => 104,  'hashrateUnit' => 'TH/s', 'power' => 3068],
        ['name' => 'Whatsminer M60S',    'coin' => 'bitcoin',  'hashrate' => 186,  'hashrateUnit' => 'TH/s', 'power' => 3441],
        ['name' => 'Antminer L7',        'coin' => 'litecoin', 'hashrate' => 9500, 'hashrateUnit' => 'MH/s', 'power' => 3425],
        ['name' => 'XMRig (Ryzen 9)',    'coin' => 'monero',   'hashrate' => 15,   'hashrateUnit' => 'KH/s', 'power' => 150],
        ['name' => 'IceRiver KS3L',      'coin' => 'kaspa',    'hashrate' => 5,    'hashrateUnit' => 'TH/s', 'power' => 3400],
    ];

    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly CategoryRepository $categoryRepository,
        private readonly CoinGeckoClient $coinGecko,
        private readonly GasTrackerService $gasTracker,
    ) {}

    // ── 1. Crypto Profit/Loss Calculator ─────────────────────────────────────

    #[Route('/tools/crypto-profit-calculator', name: 'tool_profit_calculator',
        methods: ['GET'], priority: 20)]
    public function profitCalculator(): Response
    {
        $site = $this->siteContext->getSite();

        return $this->render('frontend/tools/profit_calculator.html.twig', [
            'site'       => $site,
            'categories' => $this->categoryRepository->findActive(),
            'breadcrumbs' => [
                ['label' => 'Home',  'url' => '/'],
                ['label' => 'Tools', 'url' => '/tools/'],
                ['label' => 'Crypto Profit Calculator', 'url' => null],
            ],
            'meta_title'       => 'Crypto Profit/Loss Calculator — Free Trading P&L Tool | ' . $site->getName(),
            'meta_description' => 'Free crypto profit calculator. Calculate your cryptocurrency trading profit or loss including fees. Works for Bitcoin, Ethereum, and all altcoins.',
        ]);
    }

    // ── 2. DCA Calculator ─────────────────────────────────────────────────────

    #[Route('/tools/dca-calculator/{coin}', name: 'tool_dca_calculator',
        defaults: ['coin' => 'bitcoin'],
        requirements: ['coin' => '[a-z0-9-]+'],
        methods: ['GET'], priority: 20)]
    public function dcaCalculator(string $coin): Response
    {
        $site      = $this->siteContext->getSite();
        $topCoins  = $this->coinGecko->getTopCoins('usd', 50);
        $coinName  = 'Bitcoin';
        foreach ($topCoins as $c) {
            if ($c['id'] === $coin) {
                $coinName = $c['name'];
                break;
            }
        }

        return $this->render('frontend/tools/dca_calculator.html.twig', [
            'site'         => $site,
            'categories'   => $this->categoryRepository->findActive(),
            'top_coins'    => $topCoins,
            'selected_coin' => $coin,
            'coin_name'    => $coinName,
            'breadcrumbs'  => [
                ['label' => 'Home',  'url' => '/'],
                ['label' => 'Tools', 'url' => '/tools/'],
                ['label' => 'DCA Calculator', 'url' => null],
            ],
            'meta_title'       => 'DCA Calculator for ' . $coinName . ' — Dollar Cost Averaging | ' . $site->getName(),
            'meta_description' => 'Calculate returns of dollar-cost averaging into ' . $coinName . '. See how investing a fixed amount weekly or monthly would have performed over time.',
        ]);
    }

    // ── 3. Mining Profitability Calculator ───────────────────────────────────

    #[Route('/tools/mining-calculator/{coin}', name: 'tool_mining_calculator',
        defaults: ['coin' => 'bitcoin'],
        requirements: ['coin' => '[a-z][a-z0-9-]*'],
        methods: ['GET'], priority: 20)]
    public function miningCalculator(string $coin): Response
    {
        $site      = $this->siteContext->getSite();
        $coinData  = self::MINING_COINS[$coin] ?? self::MINING_COINS['bitcoin'];
        $coinId    = $coinData['id'];
        $coinPrice = $this->coinGecko->getPrice($coinId, 'usd');

        return $this->render('frontend/tools/mining_calculator.html.twig', [
            'site'            => $site,
            'categories'      => $this->categoryRepository->findActive(),
            'coin'            => $coin,
            'coin_data'       => $coinData,
            'coin_price'      => $coinPrice,
            'mining_coins'    => self::MINING_COINS,
            'hardware_presets'=> self::HARDWARE_PRESETS,
            'breadcrumbs'     => [
                ['label' => 'Home',  'url' => '/'],
                ['label' => 'Tools', 'url' => '/tools/'],
                ['label' => $coinData['name'] . ' Mining Calculator', 'url' => null],
            ],
            'meta_title'       => $coinData['name'] . ' Mining Calculator — Is Mining Profitable? | ' . $site->getName(),
            'meta_description' => 'Calculate ' . $coinData['name'] . ' mining profitability. Enter hashrate, power cost, and electricity to see daily, monthly, and yearly earnings.',
        ]);
    }

    // ── 4. Crypto Tax Calculator ──────────────────────────────────────────────

    #[Route('/tools/crypto-tax-calculator', name: 'tool_tax_calculator',
        methods: ['GET'], priority: 20)]
    public function taxCalculator(): Response
    {
        $site = $this->siteContext->getSite();

        return $this->render('frontend/tools/tax_calculator.html.twig', [
            'site'       => $site,
            'categories' => $this->categoryRepository->findActive(),
            'breadcrumbs' => [
                ['label' => 'Home',  'url' => '/'],
                ['label' => 'Tools', 'url' => '/tools/'],
                ['label' => 'Crypto Tax Calculator', 'url' => null],
            ],
            'meta_title'       => 'Crypto Tax Calculator 2026 — Estimate Your Crypto Taxes Free | ' . $site->getName(),
            'meta_description' => 'Free crypto tax calculator for US, UK, Germany, and Australia. Estimate your capital gains tax on cryptocurrency profits. Updated for 2026 tax year.',
        ]);
    }

    // ── 5. Gas Fee Tracker ────────────────────────────────────────────────────

    #[Route('/tools/gas-tracker/{network}', name: 'tool_gas_tracker',
        defaults: ['network' => 'ethereum'],
        requirements: ['network' => '[a-z]+'],
        methods: ['GET'], priority: 20)]
    public function gasTracker(string $network): Response
    {
        $site     = $this->siteContext->getSite();
        $gasData  = $this->gasTracker->getGasPrices($network);

        return $this->render('frontend/tools/gas_tracker.html.twig', [
            'site'       => $site,
            'categories' => $this->categoryRepository->findActive(),
            'network'    => $network,
            'gas'        => $gasData,
            'breadcrumbs' => [
                ['label' => 'Home',  'url' => '/'],
                ['label' => 'Tools', 'url' => '/tools/'],
                ['label' => 'Gas Fee Tracker', 'url' => null],
            ],
            'meta_title'       => 'Ethereum Gas Tracker — Current Gas Prices & Fee Estimator | ' . $site->getName(),
            'meta_description' => 'Live Ethereum gas prices. See current slow, average, and fast gas fees in Gwei and USD. Estimate transaction costs for transfers, swaps, and NFT mints.',
        ]);
    }
}
