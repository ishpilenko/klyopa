<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Enum\ArticleSchemaType;
use App\Enum\ArticleStatus;
use App\Repository\CategoryRepository;
use App\Repository\SiteRepository;
use App\Service\SiteContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Generates test articles for all categories of a site using Claude API.
 *
 * Usage:
 *   bin/console app:seed:articles --site-id=1
 *   bin/console app:seed:articles --site-id=1 --per-category=3
 */
#[AsCommand(
    name: 'app:seed:articles',
    description: 'Generate test articles for every category using Claude API',
)]
class SeedArticlesCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly SiteRepository $siteRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly SiteContext $siteContext,
        private readonly string $anthropicApiKey,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('site-id', null, InputOption::VALUE_REQUIRED, 'Site ID to generate articles for', 1)
            ->addOption('per-category', null, InputOption::VALUE_REQUIRED, 'Articles to generate per category', 2);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (empty($this->anthropicApiKey)) {
            $io->error('ANTHROPIC_API_KEY is not set. Add it to .env.local');
            return Command::FAILURE;
        }

        $siteId = (int) $input->getOption('site-id');
        $perCat = (int) $input->getOption('per-category');

        $site = $this->siteRepository->find($siteId);
        if (!$site) {
            $io->error("Site #$siteId not found.");
            return Command::FAILURE;
        }

        $this->siteContext->setSite($site);

        $categories = $this->categoryRepository->findBy(['site' => $site, 'isActive' => true]);
        if (empty($categories)) {
            $io->error('No active categories found for this site.');
            return Command::FAILURE;
        }

        $io->title("Generating articles for site: {$site->getName()}");
        $io->text("Categories: " . count($categories) . " | Articles per category: $perCat");

        $totalGenerated = 0;

        foreach ($categories as $category) {
            $io->section("Category: {$category->getName()}");

            $topics = $this->buildTopics($category->getSlug(), $perCat);

            foreach ($topics as $idx => $topic) {
                $io->text("[$idx] Generating: $topic ...");

                try {
                    $article = $this->generateArticle($topic, $category->getName(), $site->getName());

                    if ($article === null) {
                        $io->warning("  Failed to generate article for: $topic");
                        continue;
                    }

                    $slug = $this->makeSlug($article['title']);

                    // Skip if slug already exists
                    $existing = $this->em->getRepository(Article::class)->findOneBy([
                        'site' => $site,
                        'slug' => $slug,
                    ]);
                    if ($existing) {
                        $io->text("  Skipping (slug exists): $slug");
                        continue;
                    }

                    $entity = (new Article())
                        ->setSite($site)
                        ->setCategory($category)
                        ->setTitle($article['title'])
                        ->setSlug($slug)
                        ->setExcerpt($article['excerpt'])
                        ->setContent($article['content'])
                        ->setStatus(ArticleStatus::Published)
                        ->setSchemaType(ArticleSchemaType::NewsArticle)
                        ->setMetaTitle($article['meta_title'])
                        ->setMetaDescription($article['meta_description'])
                        ->setAuthorName($site->getName() . ' Editorial')
                        ->setIsAiGenerated(true)
                        ->setIsEvergreen(false);

                    $entity->publish();

                    $wordCount = str_word_count(strip_tags($article['content']));
                    $entity->setWordCount($wordCount);
                    $entity->setReadingTimeMinutes((int) ceil($wordCount / 200));

                    $this->em->persist($entity);
                    $this->em->flush();

                    $io->text("  <info>✓ Published:</info> {$article['title']}");
                    ++$totalGenerated;

                    // Be respectful to the API
                    sleep(2);

                } catch (\Throwable $e) {
                    $io->warning("  Error: " . $e->getMessage());
                }
            }
        }

        $io->success("Done! Generated $totalGenerated articles.");
        return Command::SUCCESS;
    }

    // ── Topic lists per category ──────────────────────────────────────────────

    /** @return string[] */
    private function buildTopics(string $categorySlug, int $count): array
    {
        $allTopics = [
            'bitcoin' => [
                'Bitcoin Price Analysis: Key Support and Resistance Levels to Watch',
                'Why Bitcoin Is Considered Digital Gold: A Deep Dive',
                'Bitcoin Halving 2024: What It Means for Investors',
                'How to Store Bitcoin Safely: Cold Wallet vs Hot Wallet',
                'Bitcoin Lightning Network: Solving the Scalability Problem',
            ],
            'ethereum' => [
                'Ethereum 2.0 Staking: How to Earn Passive Income',
                'Understanding Ethereum Smart Contracts for Beginners',
                'ETH Gas Fees Explained: Why They Fluctuate and How to Save',
                'Ethereum vs Bitcoin: Key Differences Every Investor Should Know',
                'Top Ethereum DApps Transforming Industries in 2025',
            ],
            'defi' => [
                'DeFi Explained: How Decentralized Finance Is Changing Banking',
                'Top 5 DeFi Protocols by Total Value Locked in 2025',
                'Yield Farming vs Staking: Which Earns More?',
                'How to Avoid DeFi Scams and Rug Pulls',
                'Liquidity Pools Explained: Risks and Rewards for Providers',
            ],
            'market-analysis' => [
                'Crypto Market Outlook: Key Trends to Watch This Quarter',
                'Fear and Greed Index: How to Use Market Sentiment in Trading',
                'Dollar-Cost Averaging in Crypto: A Strategy for Any Market',
                'How Macro Economic Factors Influence Crypto Prices',
                'Altcoin Season Explained: How to Identify and Profit From It',
            ],
        ];

        $topics = $allTopics[$categorySlug] ?? [
            "Latest Developments in {$categorySlug} Cryptocurrency",
            "Investment Guide for {$categorySlug} Tokens",
            "Technical Analysis of {$categorySlug} Market Trends",
        ];

        return array_slice($topics, 0, $count);
    }

    // ── Claude API call ───────────────────────────────────────────────────────

    private function generateArticle(string $topic, string $categoryName, string $siteName): ?array
    {
        $prompt = <<<PROMPT
Write a professional crypto news article for the website "{$siteName}" in the category "{$categoryName}".

Topic: {$topic}

Return a JSON object with these exact fields:
{
  "title": "SEO-optimized H1 title (max 70 chars)",
  "excerpt": "Article summary for listing pages (2-3 sentences, max 200 chars)",
  "meta_title": "SEO meta title (max 60 chars, include keyword)",
  "meta_description": "SEO meta description (max 155 chars)",
  "content": "Full article HTML content"
}

Content requirements:
- 600-900 words
- HTML format: use <h2>, <h3>, <p>, <ul>/<li> tags
- Start with a brief intro paragraph (no H2 before first paragraph)
- Include 3-4 H2 sections with detailed content
- Include 1 bullet list
- Factual, professional tone. No financial advice.
- Do NOT include the H1 title in the content (it's rendered separately)

Return valid JSON only. No markdown, no code fences, no explanation.
PROMPT;

        $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key'         => $this->anthropicApiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'json' => [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 2000,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ],
            'timeout' => 60,
        ]);

        $data = $response->toArray(false);
        $text = $data['content'][0]['text'] ?? '';

        // Strip code fences if Claude added them
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```$/m', '', $text);

        $parsed = json_decode(trim($text), true);

        if (!is_array($parsed) || empty($parsed['title']) || empty($parsed['content'])) {
            return null;
        }

        return $parsed;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeSlug(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', trim($slug));
        return substr($slug, 0, 100);
    }
}
