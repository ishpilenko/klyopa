<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CoinPage;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Generates AI descriptions and FAQ for CoinPage entities via Claude API.
 *
 * Usage:
 *   bin/console app:generate:coin-descriptions --site-id=4
 *   bin/console app:generate:coin-descriptions --site-id=4 --limit=10 --force
 *
 * Requires ANTHROPIC_API_KEY in .env
 */
#[AsCommand(
    name: 'app:generate:coin-descriptions',
    description: 'Generate AI descriptions and FAQ for coin pages using Claude API',
)]
class GenerateCoinDescriptionsCommand extends Command
{
    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    private const CLAUDE_MODEL   = 'claude-haiku-4-5-20251001';
    private const SLEEP_BETWEEN  = 2; // seconds between API calls

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SiteRepository $siteRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly string $anthropicApiKey,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('site-id', null, InputOption::VALUE_REQUIRED, 'Site ID to process')
            ->addOption('limit',   null, InputOption::VALUE_REQUIRED, 'Max coins to process per run', '20')
            ->addOption('force',   null, InputOption::VALUE_NONE,     'Re-generate even if description already exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->anthropicApiKey) {
            $io->error('ANTHROPIC_API_KEY is not configured. Set it in .env.');
            return Command::FAILURE;
        }

        $siteId = $input->getOption('site-id');
        $limit  = (int) $input->getOption('limit');
        $force  = (bool) $input->getOption('force');

        $site = $siteId ? $this->siteRepository->find((int) $siteId) : null;
        if ($siteId && !$site) {
            $io->error("Site #{$siteId} not found.");
            return Command::FAILURE;
        }

        // Find coins needing descriptions
        $qb = $this->em->getRepository(CoinPage::class)->createQueryBuilder('c');
        if ($site) {
            $qb->where('c.site = :site')->setParameter('site', $site);
        }
        if (!$force) {
            $qb->andWhere('c.description IS NULL');
        }
        $qb->setMaxResults($limit)->orderBy('c.id', 'ASC');

        /** @var CoinPage[] $coins */
        $coins = $qb->getQuery()->getResult();

        if (empty($coins)) {
            $io->info('No coins need descriptions. Use --force to regenerate existing ones.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Generating descriptions for %d coin(s)…', count($coins)));
        $processed = 0;

        foreach ($coins as $coin) {
            $io->write(sprintf('  • %s (%s)… ', $coin->getName(), $coin->getSymbol()));

            try {
                [$description, $faqJson] = $this->generateContent($coin);

                $coin->setDescription($description);
                $coin->setFaqJson($faqJson);
                $this->em->flush();

                $io->writeln('<info>done</info>');
                $processed++;

                // Respect rate limits
                if ($processed < count($coins)) {
                    sleep(self::SLEEP_BETWEEN);
                }
            } catch (\Throwable $e) {
                $io->writeln('<error>FAILED: ' . $e->getMessage() . '</error>');
            }
        }

        $io->success(sprintf('Generated descriptions for %d coin(s).', $processed));
        return Command::SUCCESS;
    }

    /**
     * @return array{string, array<int, array{q: string, a: string}>}
     */
    private function generateContent(CoinPage $coin): array
    {
        $name   = $coin->getName();
        $symbol = $coin->getSymbol();
        $site   = $coin->getSite();

        $prompt = <<<PROMPT
        Write SEO-optimized content for a cryptocurrency price page about {$name} ({$symbol})
        on {$site->getName()}, a {$site->getVertical()->value} information website.

        Provide your response as valid JSON with exactly this structure:
        {
          "description": "<p>paragraph 1</p><p>paragraph 2</p>",
          "faq": [
            {"q": "What is {$name}?", "a": "Answer in 1-2 sentences."},
            {"q": "How much is 1 {$symbol} worth?", "a": "Answer referencing current market data."},
            {"q": "What is the all-time high of {$name}?", "a": "Answer."},
            {"q": "How to buy {$name}?", "a": "Brief guidance answer."}
          ]
        }

        Requirements:
        - description: 2 short HTML paragraphs (total ~100-150 words), factual, no hype
        - Each paragraph wrapped in <p> tags
        - FAQ answers: concise, 1-3 sentences each
        - Write in English
        - Do not include price predictions or financial advice
        PROMPT;

        $response = $this->httpClient->request('POST', self::CLAUDE_API_URL, [
            'headers' => [
                'x-api-key'         => $this->anthropicApiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'json' => [
                'model'      => self::CLAUDE_MODEL,
                'max_tokens' => 800,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ],
            'timeout' => 30,
        ]);

        $data = $response->toArray();
        $text = $data['content'][0]['text'] ?? '';

        // Extract JSON from response (Claude sometimes adds text before/after)
        if (!preg_match('/\{[\s\S]*\}/', $text, $m)) {
            throw new \RuntimeException('Could not parse JSON from Claude response.');
        }

        $parsed = json_decode($m[0], true, 512, JSON_THROW_ON_ERROR);

        $description = $parsed['description'] ?? '';
        $faqJson     = $parsed['faq'] ?? [];

        if (!$description) {
            throw new \RuntimeException('Empty description in Claude response.');
        }

        return [$description, $faqJson];
    }
}
