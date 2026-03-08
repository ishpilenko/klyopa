<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\CoinGecko\CoinGeckoClient;
use App\Service\FearGreedClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Generates a daily AI market commentary for the Fear & Greed Index page.
 * Cron: daily at 10:00 UTC — bin/console app:update:fear-greed-comment
 *
 * Stores result in Redis under key 'fear_greed_comment' (TTL 26 hours).
 * Template reads this key via {{ app.session.get('fear_greed_comment') }} or
 * the controller passes it as a template variable.
 */
#[AsCommand(
    name: 'app:update:fear-greed-comment',
    description: 'Generate daily AI commentary for the Fear & Greed Index page',
)]
class UpdateFearGreedCommentCommand extends Command
{
    private const ANTHROPIC_API   = 'https://api.anthropic.com/v1/messages';
    private const CACHE_KEY       = 'fear_greed_comment';
    private const CACHE_TTL       = 93_600; // 26 hours

    public function __construct(
        private readonly FearGreedClient $fearGreedClient,
        private readonly CoinGeckoClient $coinGecko,
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $anthropicApiKey = '',
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Updating Fear & Greed commentary');

        $apiKey = $this->anthropicApiKey ?: $_ENV['ANTHROPIC_API_KEY'] ?? '';
        if (empty($apiKey)) {
            $io->error('ANTHROPIC_API_KEY env variable is not set.');
            return Command::FAILURE;
        }

        // Fetch data
        $history  = $this->fearGreedClient->getIndex(2);
        $current  = $history[0] ?? null;
        $previous = $history[1] ?? null;

        if (null === $current) {
            $io->error('Could not fetch Fear & Greed data.');
            return Command::FAILURE;
        }

        $btcPrice = null;
        $coins    = $this->coinGecko->getTopCoins('usd', 1);
        if (!empty($coins[0]['current_price'])) {
            $btcPrice = $coins[0]['current_price'];
        }

        $prompt = sprintf(
            'The Crypto Fear & Greed Index is currently %d (%s). ' .
            '%s' .
            '%s' .
            'Write a 2-3 sentence factual market commentary based on these numbers. ' .
            'Be objective and informative. Do NOT give financial advice. ' .
            'Do NOT say "I" — write in third person as an analyst summary.',
            $current['value'],
            $current['classification'],
            $previous ? "Yesterday it was {$previous['value']} ({$previous['classification']}). " : '',
            $btcPrice ? "Bitcoin price is $" . number_format($btcPrice, 0) . ". " : '',
        );

        $io->write('Calling Claude API … ');

        try {
            $response = $this->httpClient->request('POST', self::ANTHROPIC_API, [
                'headers' => [
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'json' => [
                    'model'      => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 256,
                    'messages'   => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ],
                'timeout' => 30,
            ]);

            $body    = $response->toArray();
            $comment = trim($body['content'][0]['text'] ?? '');

            if (empty($comment)) {
                $io->writeln('<comment>Empty response from API</comment>');
                return Command::FAILURE;
            }

            // Store in cache
            $this->cache->delete(self::CACHE_KEY);
            $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) use ($comment): string {
                $item->expiresAfter(self::CACHE_TTL);
                return $comment;
            });

            $io->writeln('<info>OK</info>');
            $io->writeln('Comment: ' . $comment);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->writeln('<error>ERROR: ' . $e->getMessage() . '</error>');
            $this->logger->error('UpdateFearGreedCommentCommand failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}
