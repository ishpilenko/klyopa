<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\CoinGecko\CoinGeckoClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GasTrackerService
{
    /** Gas units for common transaction types */
    private const GAS_UNITS = [
        'ETH Transfer'      => 21_000,
        'ERC-20 Transfer'   => 65_000,
        'Uniswap Swap'      => 150_000,
        'NFT Mint'          => 120_000,
        'Contract Deploy'   => 500_000,
    ];

    private const CACHE_TTL = 10; // seconds

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly CoinGeckoClient $coinGecko,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(ETHERSCAN_API_KEY)%')]
        private readonly string $etherscanApiKey,
    ) {}

    /**
     * Fetch current Ethereum gas prices.
     *
     * Returns:
     *   slow / average / fast   — gwei values
     *   ethPrice                — current ETH price in USD
     *   transactions            — array of {label, gasUnits, slow, average, fast} USD costs
     *   updatedAt               — ISO-8601 timestamp
     *   fallback                — true when Etherscan returned no data
     */
    public function getGasPrices(string $network = 'ethereum'): array
    {
        $key = 'gas_prices_' . $network;

        return $this->cache->get($key, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);
            return $this->fetchFromEtherscan();
        });
    }

    private function fetchFromEtherscan(): array
    {
        $ethPrice = $this->coinGecko->getPrice('ethereum', 'usd') ?? 3000.0;

        $slow = 5.0;
        $average = 10.0;
        $fast = 25.0;
        $fallback = true;

        try {
            $response = $this->httpClient->request('GET', 'https://api.etherscan.io/api', [
                'query'   => [
                    'module' => 'gastracker',
                    'action' => 'gasoracle',
                    'apikey' => $this->etherscanApiKey ?: 'YourApiKeyToken',
                ],
                'timeout' => 5,
            ]);

            $data = $response->toArray(false);

            if (isset($data['result']['SafeGasPrice'])) {
                $slow    = (float) $data['result']['SafeGasPrice'];
                $average = (float) $data['result']['ProposeGasPrice'];
                $fast    = (float) $data['result']['FastGasPrice'];
                $fallback = false;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Etherscan gas fetch failed', ['error' => $e->getMessage()]);
        }

        return [
            'slow'         => $slow,
            'average'      => $average,
            'fast'         => $fast,
            'ethPrice'     => $ethPrice,
            'transactions' => $this->buildTxCosts($slow, $average, $fast, $ethPrice),
            'updatedAt'    => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'fallback'     => $fallback,
        ];
    }

    /** @return array<int, array{label: string, gasUnits: int, slow: float, average: float, fast: float}> */
    private function buildTxCosts(float $slow, float $average, float $fast, float $ethPrice): array
    {
        $result = [];
        foreach (self::GAS_UNITS as $label => $units) {
            $result[] = [
                'label'    => $label,
                'gasUnits' => $units,
                'slow'     => $this->gweiToUsd($slow,    $units, $ethPrice),
                'average'  => $this->gweiToUsd($average, $units, $ethPrice),
                'fast'     => $this->gweiToUsd($fast,    $units, $ethPrice),
            ];
        }
        return $result;
    }

    /** Convert gwei × gasUnits → USD */
    private function gweiToUsd(float $gwei, int $units, float $ethPrice): float
    {
        return ($gwei * $units * 1e-9) * $ethPrice;
    }
}
