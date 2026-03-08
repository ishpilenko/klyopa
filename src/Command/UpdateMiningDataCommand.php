<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\CoinGecko\CoinGeckoClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Updates mining network data (hashrate, block reward, difficulty) in Redis cache.
 * Cron: every 6 hours — bin/console app:update:mining-data
 *
 * Data sources:
 *   BTC  — blockchain.info/stats API
 *   Others — hardcoded with known-good defaults (extend as needed)
 */
#[AsCommand(
    name: 'app:update:mining-data',
    description: 'Refresh mining network data (hashrate, difficulty) for all supported coins',
)]
class UpdateMiningDataCommand extends Command
{
    /** Cache key prefix; read by ToolsController */
    public const CACHE_PREFIX = 'mining_data_';
    public const CACHE_TTL    = 21_600; // 6 hours

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly CoinGeckoClient $coinGecko,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Updating mining network data');

        $updated = 0;

        // ── Bitcoin: fetch from blockchain.info ────────────────────────────
        try {
            $response = $this->httpClient->request('GET', 'https://blockchain.info/stats?format=json', [
                'timeout' => 8,
            ]);
            $stats = $response->toArray(false);

            if (isset($stats['hash_rate'])) {
                // blockchain.info returns hash_rate in GH/s
                $networkHashrate = (float) $stats['hash_rate'] * 1e9; // convert to H/s

                $this->storeMiningData('bitcoin', [
                    'networkHashrate' => $networkHashrate,
                    'blockReward'     => 3.125,
                    'blockTime'       => 600,
                ]);

                $io->text(sprintf('BTC: network hashrate %.0f EH/s', $networkHashrate / 1e18));
                $updated++;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to fetch BTC mining data', ['error' => $e->getMessage()]);
            $io->warning('BTC: could not fetch data — ' . $e->getMessage());
        }

        // ── Litecoin (hardcoded approximate values) ─────────────────────────
        $this->storeMiningData('litecoin', [
            'networkHashrate' => 1.5e15,  // ~1,500 TH/s
            'blockReward'     => 6.25,
            'blockTime'       => 150,
        ]);
        $io->text('LTC: stored default values (1,500 TH/s)');
        $updated++;

        // ── Monero ───────────────────────────────────────────────────────────
        $this->storeMiningData('monero', [
            'networkHashrate' => 2.8e9,  // ~2.8 GH/s
            'blockReward'     => 0.6,
            'blockTime'       => 120,
        ]);
        $io->text('XMR: stored default values (2.8 GH/s)');
        $updated++;

        // ── Kaspa ────────────────────────────────────────────────────────────
        $this->storeMiningData('kaspa', [
            'networkHashrate' => 5e14,   // ~500 TH/s
            'blockReward'     => 50.0,
            'blockTime'       => 1,
        ]);
        $io->text('KAS: stored default values (500 TH/s)');
        $updated++;

        $io->success(sprintf('Updated data for %d coins.', $updated));
        return Command::SUCCESS;
    }

    private function storeMiningData(string $coinId, array $data): void
    {
        $key = self::CACHE_PREFIX . $coinId;

        // Delete existing cached item then re-set
        $this->cache->delete($key);
        $this->cache->get($key, function ($item) use ($data): array {
            $item->expiresAfter(self::CACHE_TTL);
            return $data;
        });
    }
}
