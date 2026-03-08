<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\CoinGecko\CoinGeckoClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Internal API for the DCA (Dollar Cost Averaging) calculator.
 * Used by the dca_calculator.html.twig JavaScript to fetch historical data.
 */
class DcaApiController extends AbstractController
{
    public function __construct(
        private readonly CoinGeckoClient $coinGecko,
    ) {}

    /**
     * GET /api/v1/dca/calculate
     *
     * Query params:
     *   coin       — CoinGecko coin ID (default: bitcoin)
     *   startDate  — Y-m-d
     *   amount     — investment per period in USD (default: 100)
     *   frequency  — weekly | monthly (default: weekly)
     */
    #[Route('/api/v1/dca/calculate', name: 'api_dca_calculate', methods: ['GET'])]
    public function calculate(Request $request): JsonResponse
    {
        $coin      = $request->query->get('coin', 'bitcoin');
        $startStr  = $request->query->get('startDate', '');
        $amount    = (float) $request->query->get('amount', '100');
        $frequency = $request->query->get('frequency', 'weekly');

        if ($startStr === '') {
            return $this->json(['error' => 'Missing required parameter: startDate (Y-m-d)'], 400);
        }

        $startDate = \DateTimeImmutable::createFromFormat('Y-m-d', $startStr);
        if ($startDate === false) {
            return $this->json(['error' => 'Invalid startDate format. Use Y-m-d'], 400);
        }

        $today = new \DateTimeImmutable('today');
        if ($startDate >= $today) {
            return $this->json(['error' => 'startDate must be in the past'], 400);
        }

        if ($amount <= 0) {
            return $this->json(['error' => 'amount must be positive'], 400);
        }

        if (!in_array($frequency, ['weekly', 'monthly'], true)) {
            return $this->json(['error' => 'frequency must be "weekly" or "monthly"'], 400);
        }

        // Fetch historical price data via range endpoint
        $from = $startDate->getTimestamp();
        $to   = $today->getTimestamp();

        $chartData = $this->coinGecko->getMarketChartRange($coin, 'usd', $from, $to);

        if (!$chartData) {
            // Fallback: use getMarketChart with days count
            $days      = (int) $today->diff($startDate)->days + 1;
            $chartData = $this->coinGecko->getMarketChart($coin, 'usd', $days);
        }

        if (!$chartData) {
            return $this->json(['error' => 'Could not retrieve price data for ' . $coin], 503);
        }

        // Build DCA purchases
        $intervalMs = ($frequency === 'monthly' ? 30 : 7) * 86400 * 1000;
        $startMs    = $startDate->getTimestamp() * 1000;

        $purchases      = [];
        $totalInvested  = 0.0;
        $totalCoins     = 0.0;
        $lastPurchaseMs = null;

        foreach ($chartData as [$timestampMs, $price]) {
            if ($timestampMs < $startMs) {
                continue;
            }
            if ($price <= 0.0) {
                continue;
            }

            $isFirst    = ($lastPurchaseMs === null);
            $gapOk      = (!$isFirst && ($timestampMs - $lastPurchaseMs) >= $intervalMs);

            if (!$isFirst && !$gapOk) {
                continue;
            }

            $coinsBought   = $amount / $price;
            $totalCoins   += $coinsBought;
            $totalInvested += $amount;

            $purchases[] = [
                'date'                => date('Y-m-d', (int) ($timestampMs / 1000)),
                'price'               => $price,
                'coinsBought'         => $coinsBought,
                'totalCoinsAtDate'    => $totalCoins,
                'totalInvestedAtDate' => $totalInvested,
                'portfolioValue'      => $totalCoins * $price,
            ];

            $lastPurchaseMs = $timestampMs;
        }

        if (empty($purchases)) {
            return $this->json(['error' => 'No price data found for the selected period'], 404);
        }

        $currentPrice   = $this->coinGecko->getPrice($coin, 'usd');
        $currentValue   = $currentPrice !== null ? $totalCoins * $currentPrice : null;
        $profit         = $currentValue !== null ? $currentValue - $totalInvested : null;
        $percentReturn  = ($currentValue !== null && $totalInvested > 0.0)
            ? (($currentValue - $totalInvested) / $totalInvested) * 100.0
            : null;

        return $this->json([
            'summary'   => [
                'totalInvested'  => $totalInvested,
                'totalCoins'     => $totalCoins,
                'currentPrice'   => $currentPrice,
                'currentValue'   => $currentValue,
                'profit'         => $profit,
                'percentReturn'  => $percentReturn,
                'numPurchases'   => count($purchases),
                'avgCostPerCoin' => $totalCoins > 0.0 ? $totalInvested / $totalCoins : null,
            ],
            'purchases' => $purchases,
        ]);
    }
}
