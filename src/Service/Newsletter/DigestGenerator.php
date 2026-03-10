<?php
declare(strict_types=1);
namespace App\Service\Newsletter;

use App\Entity\NewsletterIssue;
use App\Enum\IssueStatus;
use App\Repository\ArticleRepository;
use App\Service\CoinGecko\CoinGeckoClient;
use App\Service\FearGreedClient;
use App\Service\SiteContext;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;

class DigestGenerator
{
    public function __construct(
        private readonly ArticleRepository $articleRepo,
        private readonly CoinGeckoClient $coinGecko,
        private readonly FearGreedClient $fearGreedClient,
        private readonly SiteContext $siteContext,
        private readonly Environment $twig,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $anthropicApiKey,
    ) {
    }

    public function generate(): NewsletterIssue
    {
        $site = $this->siteContext->getSite();

        // Collect articles from last 24h
        $articles = $this->articleRepo->findPublishedSince(
            new \DateTimeImmutable('-24 hours'),
            10,
        );

        // If no recent articles, get latest ones
        if (empty($articles)) {
            $articles = $this->articleRepo->findPublished(limit: 5);
        }

        $btcPrice  = $this->coinGecko->getPrice('bitcoin', 'usd') ?? 0.0;
        $ethPrice  = $this->coinGecko->getPrice('ethereum', 'usd') ?? 0.0;
        $fgHistory = $this->fearGreedClient->getIndex(1);
        $fearGreed = $fgHistory[0]['value'] ?? 50;

        // Build articles context
        $articlesContext = array_map(fn ($a) => [
            'title'    => $a->getTitle(),
            'excerpt'  => $a->getExcerpt() ?? '',
            'category' => $a->getCategory()?->getName() ?? '',
            'url'      => 'https://' . $site->getDomain()
                . '/' . ($a->getCategory()?->getSlug() ?? '')
                . '/' . $a->getSlug(),
        ], $articles);

        // AI generation
        $parsed = $this->callClaude($articlesContext, $btcPrice, $ethPrice, $fearGreed);

        // Render HTML template
        $contentHtml = $this->twig->render('email/newsletter_digest.html.twig', [
            'site'             => $site,
            'subject'          => $parsed['subject'] ?? 'Daily Crypto Brief',
            'previewText'      => $parsed['previewText'] ?? null,
            'intro'            => $parsed['intro'] ?? '',
            'marketBrief'      => $parsed['marketBrief'] ?? '',
            'articles'         => $articles,
            'articleSummaries' => $parsed['articleSummaries'] ?? [],
            'btcPrice'         => $btcPrice,
            'ethPrice'         => $ethPrice,
            'fearGreed'        => $fearGreed,
        ]);

        $issue = new NewsletterIssue();
        $issue->setSite($site);
        $issue->setSubject($parsed['subject'] ?? 'Daily Crypto Brief');
        $issue->setPreviewText($parsed['previewText'] ?? null);
        $issue->setContentHtml($contentHtml);
        $issue->setContentJson([
            'intro'            => $parsed['intro'] ?? '',
            'marketBrief'      => $parsed['marketBrief'] ?? '',
            'articleSummaries' => $parsed['articleSummaries'] ?? [],
            'btcPrice'         => $btcPrice,
            'ethPrice'         => $ethPrice,
            'fearGreed'        => $fearGreed,
        ]);
        $issue->setGeneratedBy('ai');
        $issue->setStatus(IssueStatus::Draft);

        return $issue;
    }

    private function callClaude(array $articles, float $btcPrice, float $ethPrice, int $fearGreed): array
    {
        if (empty($this->anthropicApiKey)) {
            return $this->fallbackContent($articles, $btcPrice, $ethPrice, $fearGreed);
        }

        $articlesList = '';
        foreach ($articles as $a) {
            $articlesList .= "- [{$a['category']}] {$a['title']}: {$a['excerpt']}\n";
        }

        $prompt = <<<PROMPT
You are writing the "Daily Crypto Brief" newsletter.

Market data:
- BTC: \${$btcPrice}
- ETH: \${$ethPrice}
- Fear & Greed Index: {$fearGreed}/100

Top articles from the last 24 hours:
{$articlesList}

Write a newsletter issue. Return valid JSON only (no markdown, no explanation):
{
  "subject": "Email subject line, max 60 chars, include a key number or hook",
  "previewText": "Preview text for inbox, max 100 chars",
  "intro": "2-3 sentence intro paragraph summarizing the day in crypto",
  "marketBrief": "2-3 sentences on market conditions",
  "articleSummaries": [
    {"title": "...", "summary": "1-2 sentence summary for the newsletter"}
  ]
}

Be concise, factual, no financial advice. Engaging but professional tone.
PROMPT;

        try {
            $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key'         => $this->anthropicApiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type'      => 'application/json',
                ],
                'json' => [
                    'model'      => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 1000,
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray(false);
            $text = $data['content'][0]['text'] ?? '';

            // Strip markdown code fences if present
            $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
            $text = preg_replace('/\s*```$/m', '', $text);

            return json_decode(trim($text), true) ?? $this->fallbackContent($articles, $btcPrice, $ethPrice, $fearGreed);
        } catch (\Throwable $e) {
            $this->logger->error('DigestGenerator Claude error: ' . $e->getMessage());
            return $this->fallbackContent($articles, $btcPrice, $ethPrice, $fearGreed);
        }
    }

    private function fallbackContent(array $articles, float $btcPrice, float $ethPrice, int $fearGreed): array
    {
        $summaries = array_map(fn ($a) => [
            'title'   => $a['title'],
            'summary' => $a['excerpt'],
        ], array_slice($articles, 0, 5));

        return [
            'subject'          => 'Daily Crypto Brief — ' . date('M j, Y'),
            'previewText'      => 'Today\'s top crypto news and market update',
            'intro'            => 'Here is your daily crypto briefing with the latest market updates and top stories.',
            'marketBrief'      => sprintf(
                'Bitcoin is trading at $%s. Ethereum is at $%s. Fear & Greed Index: %d/100.',
                number_format($btcPrice, 0),
                number_format($ethPrice, 0),
                $fearGreed,
            ),
            'articleSummaries' => $summaries,
        ];
    }
}
