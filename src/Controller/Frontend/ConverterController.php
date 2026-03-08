<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Repository\CategoryRepository;
use App\Service\CoinGecko\CoinGeckoClient;
use App\Service\SiteContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ConverterController extends AbstractController
{
    /** Popular amounts to show in the conversion table */
    private const POPULAR_AMOUNTS = [0.1, 0.5, 1, 5, 10, 25, 50, 100, 500, 1000];

    /** Supported fiat currencies */
    private const FIATS = ['usd', 'eur', 'gbp', 'jpy', 'aud', 'cad', 'chf', 'cny'];

    public function __construct(
        private readonly SiteContext $siteContext,
        private readonly CoinGeckoClient $coinGecko,
        private readonly CategoryRepository $categoryRepository,
    ) {}

    #[Route('/tools/converter', name: 'converter_index', methods: ['GET'], priority: 20)]
    public function index(): Response
    {
        $topCoins = $this->coinGecko->getTopCoins('usd', 20);
        $site = $this->siteContext->getSite();

        return $this->render('frontend/converter/index.html.twig', [
            'site'       => $site,
            'top_coins'  => $topCoins,
            'categories' => $this->categoryRepository->findActive(),
            'breadcrumbs' => [
                ['label' => 'Home',   'url' => '/'],
                ['label' => 'Tools',  'url' => '/tools/'],
                ['label' => 'Crypto Converter', 'url' => null],
            ],
            'meta_title'       => 'Crypto Currency Converter — ' . $site->getName(),
            'meta_description' => 'Convert between 100+ cryptocurrencies and fiat currencies. Free real-time converter.',
        ]);
    }

    #[Route('/convert/{from}-to-{to}', name: 'converter_pair', methods: ['GET'],
        requirements: ['from' => '[a-z0-9]+', 'to' => '[a-z0-9]+'],
        priority: 20,
    )]
    #[Route('/convert/{from}-to-{to}/{amount}', name: 'converter_pair_amount', methods: ['GET'],
        requirements: ['from' => '[a-z0-9]+', 'to' => '[a-z0-9]+', 'amount' => '[0-9.]+'],
        priority: 20,
    )]
    public function pair(string $from, string $to, string $amount = '1'): Response
    {
        $from   = strtolower($from);
        $to     = strtolower($to);
        $amount = max(0.000001, (float) $amount);
        $site   = $this->siteContext->getSite();

        [$fromId, $fromName, $fromSymbol, $fromIsFiat] = $this->resolveCoin($from);
        [$toId, $toName, $toSymbol, $toIsFiat]         = $this->resolveCoin($to);

        $rate   = null;
        $result = null;
        $error  = null;

        if ($fromId && $toId) {
            $rate = $this->computeRate($fromId, $fromIsFiat, $toId, $toIsFiat);
            if ($rate !== null) {
                $result = $amount * $rate;
            }
        } else {
            $error = 'Unknown currency symbol.';
        }

        // Conversion table rows
        $table = [];
        foreach (self::POPULAR_AMOUNTS as $qty) {
            $table[] = ['amount' => $qty, 'result' => $rate !== null ? $qty * $rate : null];
        }

        // Reverse table
        $reverseTable = [];
        foreach (self::POPULAR_AMOUNTS as $qty) {
            $reverseTable[] = ['amount' => $qty, 'result' => $rate > 0 ? $qty / $rate : null];
        }

        $fromDisplay = $fromSymbol ?? strtoupper($from);
        $toDisplay   = $toSymbol   ?? strtoupper($to);

        $title = sprintf('Convert %s to %s — %s to %s Converter | %s',
            $fromDisplay, $toDisplay, $fromName ?? $fromDisplay, $toName ?? $toDisplay, $site->getName());
        $desc  = sprintf('1 %s = %s %s. Real-time %s to %s exchange rate, conversion table, and FAQ.',
            $fromDisplay,
            $rate !== null ? $this->formatRate($rate) : '?',
            $toDisplay,
            $fromDisplay,
            $toDisplay,
        );

        return $this->render('frontend/converter/pair.html.twig', [
            'site'          => $site,
            'categories'    => $this->categoryRepository->findActive(),
            'from'          => $from,
            'to'            => $to,
            'from_id'       => $fromId,
            'to_id'         => $toId,
            'from_name'     => $fromName ?? strtoupper($from),
            'to_name'       => $toName   ?? strtoupper($to),
            'from_symbol'   => $fromDisplay,
            'to_symbol'     => $toDisplay,
            'amount'        => $amount,
            'rate'          => $rate,
            'result'        => $result,
            'error'         => $error,
            'table'         => $table,
            'reverse_table' => $reverseTable,
            'breadcrumbs'   => [
                ['label' => 'Home',      'url' => '/'],
                ['label' => 'Tools',     'url' => '/tools/'],
                ['label' => 'Converter', 'url' => '/tools/converter'],
                ['label' => $fromDisplay . ' to ' . $toDisplay, 'url' => null],
            ],
            'meta_title'       => $title,
            'meta_description' => $desc,
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * @return array{string|null, string|null, string|null, bool}  [coinGeckoId, name, symbol, isFiat]
     */
    private function resolveCoin(string $sym): array
    {
        if ($this->coinGecko->isFiatCurrency($sym)) {
            return [strtolower($sym), strtoupper($sym), strtoupper($sym), true];
        }

        $id = $this->coinGecko->findCoinIdBySymbol($sym);
        if (!$id) {
            return [null, null, null, false];
        }

        // Get display name from top-coins cache if possible
        $info = $this->coinGecko->getCoinInfo($id);
        $name   = $info['name']   ?? ucfirst($id);
        $symbol = $info['symbol'] ?? strtoupper($sym);

        return [$id, $name, strtoupper($symbol), false];
    }

    private function computeRate(string $fromId, bool $fromIsFiat, string $toId, bool $toIsFiat): ?float
    {
        if ($fromIsFiat && $toIsFiat) {
            // fiat-to-fiat: not supported for now
            return null;
        }

        if (!$fromIsFiat && !$toIsFiat) {
            // crypto-to-crypto: cross via USD
            $fromUsd = $this->coinGecko->getPrice($fromId, 'usd');
            $toUsd   = $this->coinGecko->getPrice($toId, 'usd');
            if ($fromUsd === null || $toUsd === null || $toUsd === 0.0) {
                return null;
            }
            return $fromUsd / $toUsd;
        }

        if (!$fromIsFiat && $toIsFiat) {
            // crypto → fiat
            return $this->coinGecko->getPrice($fromId, $toId);
        }

        // fiat → crypto
        $price = $this->coinGecko->getPrice($toId, $fromId);
        return $price > 0 ? 1.0 / $price : null;
    }

    private function formatRate(float $rate): string
    {
        if ($rate >= 1_000_000) {
            return number_format($rate, 2);
        }
        if ($rate >= 100) {
            return number_format($rate, 2);
        }
        if ($rate >= 1) {
            return number_format($rate, 4);
        }
        return rtrim(number_format($rate, 8), '0');
    }
}
