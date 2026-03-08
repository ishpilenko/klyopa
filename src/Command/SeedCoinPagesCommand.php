<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CoinPage;
use App\Entity\Site;
use App\Repository\SiteRepository;
use App\Service\CoinGecko\CoinGeckoClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:coin-pages',
    description: 'Seed CoinPage records from CoinGecko top coins (used for /price/{slug} pages)',
)]
class SeedCoinPagesCommand extends Command
{
    public function __construct(
        private readonly CoinGeckoClient $coinGecko,
        private readonly SiteRepository $siteRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('site-id', null, InputOption::VALUE_REQUIRED, 'Site ID to seed for (omit = all crypto sites)')
            ->addOption('count',   null, InputOption::VALUE_REQUIRED, 'Number of top coins to seed', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count  = (int) $input->getOption('count');
        $siteId = $input->getOption('site-id');

        // Resolve target sites
        $sites = $siteId
            ? array_filter([$this->siteRepository->find((int) $siteId)])
            : $this->siteRepository->findBy(['vertical' => 'crypto', 'isActive' => true]);

        if (empty($sites)) {
            $io->error('No matching sites found.');
            return Command::FAILURE;
        }

        $io->info(sprintf('Fetching top %d coins from CoinGecko…', $count));
        $topCoins = $this->coinGecko->getTopCoins('usd', min($count, 250));

        if (empty($topCoins)) {
            $io->error('CoinGecko returned no coins. Check API availability.');
            return Command::FAILURE;
        }

        $io->info(sprintf('Got %d coins. Seeding for %d site(s)…', count($topCoins), count($sites)));

        foreach ($sites as $site) {
            $created = 0;
            $updated = 0;

            foreach ($topCoins as $coin) {
                $coinId = $coin['id']     ?? null;
                $symbol = $coin['symbol'] ?? null;
                $name   = $coin['name']   ?? null;
                $image  = $coin['image']  ?? null;

                if (!$coinId || !$symbol || !$name) {
                    continue;
                }

                $slug = strtolower(preg_replace('/[^a-z0-9-]/', '-', $coinId));

                // Check existing
                $existing = $this->em->getRepository(CoinPage::class)->findOneBy([
                    'site'        => $site,
                    'coinGeckoId' => $coinId,
                ]);

                if ($existing) {
                    // Update image if changed
                    if ($image && $existing->getImageUrl() !== $image) {
                        $existing->setImageUrl($image);
                        $updated++;
                    }
                    continue;
                }

                $coinPage = new CoinPage();
                $coinPage->setSite($site)
                         ->setCoinGeckoId($coinId)
                         ->setSymbol(strtoupper($symbol))
                         ->setName($name)
                         ->setSlug($slug)
                         ->setImageUrl($image)
                         ->setIsActive(true);

                $this->em->persist($coinPage);
                $created++;
            }

            $this->em->flush();
            $io->success(sprintf('Site "%s": created %d, updated %d coin pages.', $site->getName(), $created, $updated));
        }

        return Command::SUCCESS;
    }
}
