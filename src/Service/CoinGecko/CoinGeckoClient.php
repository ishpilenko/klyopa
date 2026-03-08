<?php

declare(strict_types=1);

namespace App\Service\CoinGecko;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CoinGeckoClient
{
    private const BASE_URL = 'https://api.coingecko.com/api/v3';
    private const TTL_PRICE     = 60;       // 1 min — live prices
    private const TTL_COIN_INFO = 300;      // 5 min — coin detail
    private const TTL_HISTORY   = 86400;    // 24 h  — historical
    private const TTL_LIST      = 86400;    // 24 h  — coin list
    private const TTL_MARKETS   = 180;      // 3 min — top coins
    private const TIMEOUT       = 8;        // HTTP timeout seconds

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Current price of a coin in a fiat/vs currency.
     * @return float|null null on error
     */
    public function getPrice(string $coinId, string $vsCurrency = 'usd'): ?float
    {
        $key = sprintf('cg_price_%s_%s', $coinId, $vsCurrency);

        return $this->cache->get($key, function (ItemInterface $item) use ($coinId, $vsCurrency): ?float {
            $item->expiresAfter(self::TTL_PRICE);

            $data = $this->fetch('/simple/price', [
                'ids'           => $coinId,
                'vs_currencies' => $vsCurrency,
            ]);

            return isset($data[$coinId][$vsCurrency]) ? (float) $data[$coinId][$vsCurrency] : null;
        });
    }

    /**
     * Prices of multiple coins in one request.
     * @param  string[] $coinIds
     * @return array<string, float>
     */
    public function getPrices(array $coinIds, string $vsCurrency = 'usd'): array
    {
        $key = sprintf('cg_prices_%s_%s', implode(',', $coinIds), $vsCurrency);

        return $this->cache->get($key, function (ItemInterface $item) use ($coinIds, $vsCurrency): array {
            $item->expiresAfter(self::TTL_PRICE);

            $data = $this->fetch('/simple/price', [
                'ids'           => implode(',', $coinIds),
                'vs_currencies' => $vsCurrency,
                'include_24hr_change' => 'true',
            ]);

            $result = [];
            foreach ($data as $id => $prices) {
                $result[$id] = $prices[$vsCurrency] ?? 0.0;
            }

            return $result;
        });
    }

    /**
     * Full coin info (name, symbol, description, market data, ATH, etc.)
     */
    public function getCoinInfo(string $coinId): ?array
    {
        $key = 'cg_info_' . $coinId;

        return $this->cache->get($key, function (ItemInterface $item) use ($coinId): ?array {
            $item->expiresAfter(self::TTL_COIN_INFO);

            return $this->fetch('/coins/' . $coinId, [
                'localization'   => 'false',
                'tickers'        => 'false',
                'community_data' => 'false',
                'developer_data' => 'false',
            ]);
        });
    }

    /**
     * Price chart data (OHLC or market chart).
     * Returns array of [timestamp_ms, price] pairs.
     */
    public function getMarketChart(string $coinId, string $vsCurrency = 'usd', int $days = 7): ?array
    {
        $key = sprintf('cg_chart_%s_%s_%d', $coinId, $vsCurrency, $days);

        return $this->cache->get($key, function (ItemInterface $item) use ($coinId, $vsCurrency, $days): ?array {
            $item->expiresAfter($days > 1 ? self::TTL_HISTORY : self::TTL_PRICE);

            $data = $this->fetch('/coins/' . $coinId . '/market_chart', [
                'vs_currency' => $vsCurrency,
                'days'        => (string) $days,
                'interval'    => $days <= 1 ? 'hourly' : 'daily',
            ]);

            return $data['prices'] ?? null;
        });
    }

    /**
     * Historical price for a specific date.
     */
    public function getHistoricalPrice(string $coinId, \DateTimeInterface $date, string $vsCurrency = 'usd'): ?float
    {
        $dateStr = $date->format('d-m-Y');
        $key = sprintf('cg_hist_%s_%s_%s', $coinId, $dateStr, $vsCurrency);

        return $this->cache->get($key, function (ItemInterface $item) use ($coinId, $dateStr, $vsCurrency): ?float {
            $item->expiresAfter(self::TTL_HISTORY);

            $data = $this->fetch('/coins/' . $coinId . '/history', [
                'date'         => $dateStr,
                'localization' => 'false',
            ]);

            return $data['market_data']['current_price'][$vsCurrency] ?? null;
        });
    }

    /**
     * Top coins by market cap.
     * @return array<int, array{id: string, symbol: string, name: string, current_price: float, price_change_percentage_24h: float, market_cap: float, image: string}>
     */
    public function getTopCoins(string $vsCurrency = 'usd', int $perPage = 100): array
    {
        $key = sprintf('cg_markets_%s_%d', $vsCurrency, $perPage);

        return $this->cache->get($key, function (ItemInterface $item) use ($vsCurrency, $perPage): array {
            $item->expiresAfter(self::TTL_MARKETS);

            $data = $this->fetch('/coins/markets', [
                'vs_currency' => $vsCurrency,
                'order'       => 'market_cap_desc',
                'per_page'    => (string) $perPage,
                'page'        => '1',
                'sparkline'   => 'false',
            ]);

            return is_array($data) ? $data : [];
        });
    }

    /**
     * Full list of all coins (id, symbol, name).
     * Cached 24h — used for symbol→id lookup.
     */
    public function getCoinList(): array
    {
        return $this->cache->get('cg_coin_list', function (ItemInterface $item): array {
            $item->expiresAfter(self::TTL_LIST);

            $data = $this->fetch('/coins/list');

            return is_array($data) ? $data : [];
        });
    }

    /**
     * Known symbol → CoinGecko ID mappings for top coins.
     * Avoids false positives in the coin list (e.g. "batcat" matching "btc").
     */
    private const SYMBOL_MAP = [
        'btc'   => 'bitcoin',
        'eth'   => 'ethereum',
        'usdt'  => 'tether',
        'bnb'   => 'binancecoin',
        'sol'   => 'solana',
        'xrp'   => 'ripple',
        'usdc'  => 'usd-coin',
        'ada'   => 'cardano',
        'avax'  => 'avalanche-2',
        'doge'  => 'dogecoin',
        'trx'   => 'tron',
        'link'  => 'chainlink',
        'dot'   => 'polkadot',
        'matic' => 'matic-network',
        'pol'   => 'matic-network',
        'ltc'   => 'litecoin',
        'bch'   => 'bitcoin-cash',
        'xlm'   => 'stellar',
        'etc'   => 'ethereum-classic',
        'atom'  => 'cosmos',
        'icp'   => 'internet-computer',
        'shib'  => 'shiba-inu',
        'uni'   => 'uniswap',
        'near'  => 'near',
        'apt'   => 'aptos',
        'fil'   => 'filecoin',
        'arb'   => 'arbitrum',
        'op'    => 'optimism',
        'sui'   => 'sui',
        'inj'   => 'injective-protocol',
        'algo'  => 'algorand',
        'vet'   => 'vechain',
        'hbar'  => 'hedera-hashgraph',
        'kas'   => 'kaspa',
        'stx'   => 'blockstack',
        'mkr'   => 'maker',
        'aave'  => 'aave',
        'sand'  => 'the-sandbox',
        'mana'  => 'decentraland',
        'crv'   => 'curve-dao-token',
        'ldo'   => 'lido-dao',
    ];

    /**
     * Find a coin by its ticker symbol (e.g. "btc" → "bitcoin").
     * Returns the CoinGecko coin ID or null if not found.
     */
    public function findCoinIdBySymbol(string $symbol): ?string
    {
        $symbol = strtolower($symbol);

        // Check hardcoded map first (avoids false positives in full coin list)
        if (isset(self::SYMBOL_MAP[$symbol])) {
            return self::SYMBOL_MAP[$symbol];
        }

        $list = $this->getCoinList();

        // Prefer exact symbol match; prioritise coins whose ID equals the symbol name
        $matches = array_filter($list, fn($c) => strtolower($c['symbol']) === $symbol);
        if (!$matches) {
            return null;
        }

        // Prefer coin whose ID matches the symbol exactly
        foreach ($matches as $coin) {
            if ($coin['id'] === $symbol) {
                return $coin['id'];
            }
        }

        // Prefer coin whose name starts with the symbol (e.g. symbol "eth" → name "Ethereum")
        foreach ($matches as $coin) {
            if (str_starts_with(strtolower($coin['name']), $symbol)) {
                return $coin['id'];
            }
        }

        // Prefer shortest ID (usually the canonical coin, not a fork/token)
        usort($matches, fn($a, $b) => strlen($a['id']) <=> strlen($b['id']));

        return array_values($matches)[0]['id'];
    }

    /**
     * Known fiat / vs-currency symbols.
     */
    public function isFiatCurrency(string $symbol): bool
    {
        $fiats = ['usd', 'eur', 'gbp', 'jpy', 'aud', 'cad', 'chf', 'cny', 'rub', 'brl',
                  'inr', 'krw', 'mxn', 'nzd', 'sgd', 'hkd', 'sek', 'nok', 'dkk', 'pln'];

        return in_array(strtolower($symbol), $fiats, true);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function fetch(string $path, array $query = []): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . $path, [
                'query'   => $query,
                'timeout' => self::TIMEOUT,
                'headers' => ['Accept' => 'application/json'],
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('CoinGecko non-200 response', [
                    'path'   => $path,
                    'status' => $response->getStatusCode(),
                ]);
                return null;
            }

            return $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->error('CoinGecko request failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
