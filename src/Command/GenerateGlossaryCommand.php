<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\GlossaryTerm;
use App\Repository\GlossaryTermRepository;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Generates glossary terms via Claude API.
 *
 * Usage:
 *   bin/console app:generate:glossary --site-id=1
 *   bin/console app:generate:glossary --site-id=1 --limit=10 --skip-existing
 *
 * Requires ANTHROPIC_API_KEY env variable.
 */
#[AsCommand(
    name: 'app:generate:glossary',
    description: 'Generate crypto glossary terms via Claude API',
)]
class GenerateGlossaryCommand extends Command
{
    /** @var string[] */
    private const TERMS = [
        'Blockchain', 'Bitcoin', 'Ethereum', 'Smart Contract', 'DeFi',
        'NFT', 'Token', 'Altcoin', 'Staking', 'Mining', 'Wallet',
        'Private Key', 'Public Key', 'Gas Fee', 'Consensus Mechanism',
        'Proof of Work', 'Proof of Stake', 'Liquidity Pool', 'Yield Farming',
        'Impermanent Loss', 'DAO', 'Airdrop', 'ICO', 'IDO', 'Whitepaper',
        'Market Cap', 'Circulating Supply', 'HODL', 'FOMO', 'FUD',
        'Whale', 'Rug Pull', 'DEX', 'CEX', 'AMM', 'Oracle',
        'Layer 1', 'Layer 2', 'Sidechain', 'Bridge', 'Rollup',
        'Cold Wallet', 'Hot Wallet', 'Seed Phrase', 'Hash Rate',
        'Block Reward', 'Halving', 'Fork', 'Hard Fork', 'Soft Fork',
        'Metaverse', 'Web3', 'dApp', 'TVL', 'APY', 'APR',
        'Slippage', 'MEV', 'Flash Loan', 'Wrapped Token',
        'Stablecoin', 'Wrapped Bitcoin', 'DeFi Protocol', 'Liquidity Mining',
        'Governance Token', 'Vault', 'Auto-compounding', 'Rebase',
        'Perpetual Contract', 'Futures', 'Options', 'Leverage',
        'Margin Trading', 'Liquidation', 'Funding Rate', 'Open Interest',
        'Bull Market', 'Bear Market', 'Correction', 'ATH', 'ATL',
        'Market Cycle', 'Dollar-Cost Averaging', 'Portfolio Rebalancing',
        'Tax-Loss Harvesting', 'Capital Gains Tax', 'Crypto Tax',
        'Know Your Customer', 'Anti-Money Laundering', 'Decentralization',
        'Interoperability', 'Cross-chain', 'Atomic Swap', 'Orderbook',
        'Market Order', 'Limit Order', 'Stop Loss', 'Take Profit',
        'Volatility', 'Correlation', 'Beta', 'Sharpe Ratio',
        'ZK-Proof', 'Zero Knowledge Proof', 'Merkle Tree', 'Hash Function',
        'Cryptography', 'Public Key Infrastructure', 'Multisig',
        'Hardware Wallet', 'Software Wallet', 'Custodial Wallet',
        'Non-custodial Wallet', 'Self-custody', 'Recovery Phrase',
        'Tokenomics', 'Vesting', 'Cliff Period', 'Token Burn',
        'Buyback', 'Treasury', 'Protocol Revenue', 'Real Yield',
        'EVM', 'Solidity', 'Rust', 'Gas Limit', 'Gas Price',
        'ERC-20', 'ERC-721', 'ERC-1155', 'BEP-20', 'SPL Token',
    ];

    private const ANTHROPIC_API = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GlossaryTermRepository $glossaryRepo,
        private readonly SiteRepository $siteRepo,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $anthropicApiKey = '',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('site-id', null, InputOption::VALUE_REQUIRED, 'Site ID to generate terms for')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max number of terms to generate', 10)
            ->addOption('skip-existing', null, InputOption::VALUE_NONE, 'Skip terms that already exist')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print prompts without calling API');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $siteId = (int) $input->getOption('site-id');
        if ($siteId <= 0) {
            $io->error('--site-id is required');
            return Command::FAILURE;
        }

        $site = $this->siteRepo->find($siteId);
        if (null === $site) {
            $io->error("Site with ID {$siteId} not found.");
            return Command::FAILURE;
        }

        $apiKey = $this->anthropicApiKey ?: $_ENV['ANTHROPIC_API_KEY'] ?? '';
        if (empty($apiKey) && !$input->getOption('dry-run')) {
            $io->error('ANTHROPIC_API_KEY env variable is not set.');
            return Command::FAILURE;
        }

        $limit       = (int) $input->getOption('limit');
        $skipExisting = (bool) $input->getOption('skip-existing');
        $dryRun      = (bool) $input->getOption('dry-run');

        $io->title("Generating glossary terms for site: {$site->getName()}");

        $generated = 0;
        $skipped   = 0;

        foreach (self::TERMS as $termName) {
            if ($generated >= $limit) {
                break;
            }

            $slug = $this->termToSlug($termName);

            if ($skipExisting) {
                // Quick check via direct query (SiteFilter is not active in CLI)
                $existing = $this->em->getRepository(GlossaryTerm::class)->findOneBy([
                    'site' => $site,
                    'slug' => $slug,
                ]);
                if (null !== $existing) {
                    $io->writeln("  [skip] {$termName}");
                    $skipped++;
                    continue;
                }
            }

            if ($dryRun) {
                $io->writeln("  [dry-run] Would generate: {$termName}");
                $generated++;
                continue;
            }

            $io->write("  Generating: {$termName} … ");

            try {
                $data = $this->generateTerm($termName, $site->getName(), $apiKey);
                if (null === $data) {
                    $io->writeln('<comment>SKIP (API returned null)</comment>');
                    continue;
                }

                $term = new GlossaryTerm();
                $term->setSite($site)
                    ->setTerm($termName)
                    ->setSlug($slug)
                    ->setShortDefinition($data['shortDefinition'] ?? '')
                    ->setFullContent($data['fullContent'] ?? '')
                    ->setFirstLetter(strtoupper($termName[0]))
                    ->setMetaTitle($data['metaTitle'] ?? null)
                    ->setMetaDescription($data['metaDescription'] ?? null)
                    ->setStatus('published');

                if (!empty($data['faq']) && is_array($data['faq'])) {
                    $term->setFaqJson($data['faq']);
                }

                if (!empty($data['relatedTerms']) && is_array($data['relatedTerms'])) {
                    $term->setRelatedTermSlugs(array_map(
                        fn (string $t) => $this->termToSlug($t),
                        $data['relatedTerms']
                    ));
                }

                $this->em->persist($term);
                $this->em->flush();

                $io->writeln('<info>OK</info>');
                $generated++;

                // Rate limit: 1 request per second
                usleep(1_100_000);
            } catch (\Throwable $e) {
                $io->writeln('<error>ERROR: ' . $e->getMessage() . '</error>');
                $this->logger->error('GenerateGlossaryCommand: failed for ' . $termName, ['error' => $e->getMessage()]);
            }
        }

        $io->success("Generated {$generated} terms, skipped {$skipped}.");
        return Command::SUCCESS;
    }

    /** @return array{shortDefinition: string, fullContent: string, faq: array, relatedTerms: array, metaTitle: string, metaDescription: string}|null */
    private function generateTerm(string $term, string $siteName, string $apiKey): ?array
    {
        $prompt = <<<PROMPT
Write a glossary entry for the cryptocurrency term "{$term}" for the website "{$siteName}".

Return ONLY valid JSON with these exact fields:
{
  "shortDefinition": "1-2 sentence plain-English definition",
  "fullContent": "HTML content 500-800 words. Use H2 subheadings. Cover: what it is, how it works, why it matters, real-world example. No markdown — only HTML tags.",
  "faq": [
    {"question": "...", "answer": "..."},
    {"question": "...", "answer": "..."},
    {"question": "...", "answer": "..."}
  ],
  "relatedTerms": ["Related Term 1", "Related Term 2", "Related Term 3"],
  "metaTitle": "What is {$term}? Definition & Guide | {$siteName}",
  "metaDescription": "150-character meta description"
}

Important: Return ONLY the JSON object. No markdown code blocks, no explanation.
PROMPT;

        $response = $this->httpClient->request('POST', self::ANTHROPIC_API, [
            'headers' => [
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'json' => [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 2048,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ],
            'timeout' => 60,
        ]);

        $body = $response->toArray();
        $text = $body['content'][0]['text'] ?? '';

        // Strip markdown code fences if present
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```$/m', '', $text);
        $text = trim($text ?? '');

        $data = json_decode($text, true);
        if (!is_array($data)) {
            $this->logger->warning('GenerateGlossaryCommand: failed to parse JSON for ' . $term, ['raw' => $text]);
            return null;
        }

        return $data;
    }

    private function termToSlug(string $term): string
    {
        $slug = mb_strtolower($term);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
        return trim($slug, '-');
    }
}
