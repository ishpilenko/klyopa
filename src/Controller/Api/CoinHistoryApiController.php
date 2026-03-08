<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\CoinGecko\CoinGeckoClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Internal API endpoint used by the investment calculator JS and n8n.
 */
class CoinHistoryApiController extends AbstractController
{
    public function __construct(
        private readonly CoinGeckoClient $coinGecko,
    ) {}

    #[Route('/api/v1/coin-history/{coinId}', name: 'api_coin_history', methods: ['GET'],
        requirements: ['coinId' => '[a-z0-9-]+'],
    )]
    public function getCoinHistory(string $coinId, Request $request): JsonResponse
    {
        $dateStr    = $request->query->get('date', '');
        $vsCurrency = strtolower($request->query->get('vs_currency', 'usd'));

        if ($dateStr === '') {
            return $this->json(['error' => 'Missing required parameter: date (Y-m-d)'], 400);
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
        if ($date === false) {
            return $this->json(['error' => 'Invalid date format. Use Y-m-d (e.g. 2020-01-15)'], 400);
        }

        if ($date >= new \DateTimeImmutable('today')) {
            return $this->json(['error' => 'Date must be in the past'], 400);
        }

        $historicalPrice = $this->coinGecko->getHistoricalPrice($coinId, $date, $vsCurrency);
        $currentPrice    = $this->coinGecko->getPrice($coinId, $vsCurrency);

        if ($historicalPrice === null) {
            return $this->json(['error' => 'Historical price not available for this coin/date'], 404);
        }

        return $this->json([
            'coin'          => $coinId,
            'date'          => $dateStr,
            'vs_currency'   => $vsCurrency,
            'price'         => $historicalPrice,
            'current_price' => $currentPrice,
        ]);
    }
}
