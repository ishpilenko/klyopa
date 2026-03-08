<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FearGreedClient
{
    private const API_URL   = 'https://api.alternative.me/fng/';
    private const CACHE_TTL = 1800; // 30 minutes

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Returns the latest N days of Fear & Greed data.
     *
     * Each item: ['value' => int, 'classification' => string, 'timestamp' => int, 'date' => \DateTimeImmutable]
     *
     * @return array<int, array{value: int, classification: string, timestamp: int, date: \DateTimeImmutable}>
     */
    public function getIndex(int $limit = 30): array
    {
        $cacheKey = 'fear_greed_' . $limit;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($limit): array {
            $item->expiresAfter(self::CACHE_TTL);

            try {
                $response = $this->httpClient->request('GET', self::API_URL, [
                    'query'   => ['limit' => $limit, 'format' => 'json'],
                    'timeout' => 5,
                ]);

                $data = $response->toArray();
                $entries = $data['data'] ?? [];

                return array_map(static function (array $entry): array {
                    return [
                        'value'          => (int) $entry['value'],
                        'classification' => $entry['value_classification'],
                        'timestamp'      => (int) $entry['timestamp'],
                        'date'           => new \DateTimeImmutable('@' . $entry['timestamp']),
                    ];
                }, $entries);
            } catch (\Throwable $e) {
                $this->logger->warning('FearGreedClient: API request failed', ['error' => $e->getMessage()]);

                // Return a fallback neutral value
                return [[
                    'value'          => 50,
                    'classification' => 'Neutral',
                    'timestamp'      => time(),
                    'date'           => new \DateTimeImmutable(),
                ]];
            }
        });
    }

    /**
     * Returns the classification zone colour for CSS/rendering.
     */
    public static function getZoneColor(int $value): string
    {
        return match (true) {
            $value <= 25  => '#ea4335', // Extreme Fear — red
            $value <= 45  => '#fbbc05', // Fear — yellow
            $value <= 55  => '#9e9e9e', // Neutral — grey
            $value <= 75  => '#34a853', // Greed — green
            default       => '#1a73e8', // Extreme Greed — blue
        };
    }
}
