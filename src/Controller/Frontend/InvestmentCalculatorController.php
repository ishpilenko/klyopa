<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Repository\CategoryRepository;
use App\Service\CoinGecko\CoinGeckoClient;
use App\Service\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InvestmentCalculatorController extends AbstractController
{
    /** Min date we can look up historical prices */
    private const MIN_DATE = '2013-01-01';

    /** Popular pre-calculated scenarios shown below the calculator */
    private const SCENARIOS = [
        ['coin' => 'bitcoin',  'amount' => 100,  'date' => '2015-01-01', 'label' => '$100 in Bitcoin (Jan 2015)'],
        ['coin' => 'bitcoin',  'amount' => 1000, 'date' => '2017-01-01', 'label' => '$1,000 in Bitcoin (Jan 2017)'],
        ['coin' => 'ethereum', 'amount' => 1000, 'date' => '2017-01-01', 'label' => '$1,000 in Ethereum (Jan 2017)'],
        ['coin' => 'solana',   'amount' => 1000, 'date' => '2020-01-01', 'label' => '$1,000 in Solana (Jan 2020)'],
        ['coin' => 'bitcoin',  'amount' => 1000, 'date' => '2020-01-01', 'label' => '$1,000 in Bitcoin (Jan 2020)'],
        ['coin' => 'ethereum', 'amount' => 500,  'date' => '2020-01-01', 'label' => '$500 in Ethereum (Jan 2020)'],
    ];

    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly CoinGeckoClient $coinGecko,
        private readonly CategoryRepository $categoryRepository,
    ) {}

    #[Route('/tools/investment-calculator/{coin}', name: 'investment_calculator',
        defaults: ['coin' => 'bitcoin'],
        requirements: ['coin' => '[a-z0-9-]+'],
        methods: ['GET'],
        priority: 20,
    )]
    public function calculate(Request $request, string $coin): Response
    {
        $site = $this->siteContext->getSite();

        // Inputs from query string
        $amountInput = $request->query->get('amount', '');
        $dateInput   = $request->query->get('date', '');

        $amount = $amountInput !== '' ? (float) $amountInput : null;
        $date   = null;
        $result = null;
        $error  = null;

        // Validate date
        if ($dateInput !== '') {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $dateInput);
            if ($dt === false || $dt > new \DateTimeImmutable('yesterday') || $dateInput < self::MIN_DATE) {
                $error = 'Please enter a valid past date (between ' . self::MIN_DATE . ' and yesterday).';
            } else {
                $date = $dt;
            }
        }

        // Get coin info (for name/symbol)
        $info = $this->coinGecko->getCoinInfo($coin);
        $coinName   = $info['name']   ?? ucfirst($coin);
        $coinSymbol = strtoupper($info['symbol'] ?? $coin);

        // Calculate if we have all inputs
        if ($amount !== null && $date !== null && $error === null && $amount > 0) {
            $historicalPrice = $this->coinGecko->getHistoricalPrice($coin, $date);
            $currentPrice    = $this->coinGecko->getPrice($coin, 'usd');

            if ($historicalPrice === null || $historicalPrice <= 0) {
                $error = 'Could not retrieve historical price data for this date. Please try a different date.';
            } elseif ($currentPrice === null || $currentPrice <= 0) {
                $error = 'Could not retrieve current price data. Please try again.';
            } else {
                $coinsAcquired = $amount / $historicalPrice;
                $currentValue  = $coinsAcquired * $currentPrice;
                $profit        = $currentValue - $amount;
                $percentReturn = (($currentValue - $amount) / $amount) * 100.0;

                $result = [
                    'historical_price' => $historicalPrice,
                    'current_price'    => $currentPrice,
                    'coins_acquired'   => $coinsAcquired,
                    'current_value'    => $currentValue,
                    'profit'           => $profit,
                    'percent_return'   => $percentReturn,
                    'is_profit'        => $profit >= 0,
                ];
            }
        }

        // Top coins for the dropdown
        $topCoins = $this->coinGecko->getTopCoins('usd', 50);

        // Build page title
        $title = sprintf('If I Had Invested in %s — Investment Calculator | %s', $coinName, $site->getName());
        $desc  = sprintf(
            'Calculate what your %s investment would be worth today. %s historical price calculator — see your returns.',
            $coinName, $coinName
        );

        return $this->render('frontend/tools/investment_calculator.html.twig', [
            'site'        => $site,
            'categories'  => $this->categoryRepository->findActive(),
            'coin'        => $coin,
            'coin_name'   => $coinName,
            'coin_symbol' => $coinSymbol,
            'coin_image'  => $info['image']['small'] ?? null,
            'amount'      => $amount,
            'date'        => $dateInput,
            'result'      => $result,
            'error'       => $error,
            'top_coins'   => $topCoins,
            'scenarios'   => self::SCENARIOS,
            'breadcrumbs' => [
                ['label' => 'Home',           'url' => '/'],
                ['label' => 'Tools',          'url' => '/tools/'],
                ['label' => 'Investment Calculator', 'url' => null],
            ],
            'meta_title'       => $title,
            'meta_description' => $desc,
        ]);
    }
}
