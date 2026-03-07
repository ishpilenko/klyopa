<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ApiToken;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-api-token',
    description: 'Generate a Bearer API token for a site (used by n8n and external integrations)',
)]
class CreateApiTokenCommand extends Command
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('site-id', InputArgument::REQUIRED, 'Site ID to create the token for')
            ->addArgument('name', InputArgument::OPTIONAL, 'Token description/name', 'n8n integration')
            ->addOption('expires', null, InputOption::VALUE_OPTIONAL, 'Expiry date (e.g. "+1 year")', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $siteId = (int) $input->getArgument('site-id');
        $site = $this->siteRepository->find($siteId);

        if (!$site) {
            $io->error("Site with ID {$siteId} not found.");
            return Command::FAILURE;
        }

        $expiresAt = null;
        if ($expiresOption = $input->getOption('expires')) {
            $expiresAt = new \DateTimeImmutable($expiresOption);
        }

        $token = bin2hex(random_bytes(32)); // 64 hex chars

        $apiToken = new ApiToken();
        $apiToken->setSite($site)
            ->setToken($token)
            ->setName($input->getArgument('name'))
            ->setExpiresAt($expiresAt);

        $this->em->persist($apiToken);
        $this->em->flush();

        $io->success("API token created for site: {$site->getDomain()} ({$site->getName()})");
        $io->table(
            ['Field', 'Value'],
            [
                ['Token ID', $apiToken->getId()],
                ['Site', $site->getDomain()],
                ['Name', $apiToken->getName()],
                ['Token', $token],
                ['Expires', $expiresAt ? $expiresAt->format('Y-m-d') : 'never'],
            ]
        );
        $io->note('Store this token securely — it will not be shown again.');

        return Command::SUCCESS;
    }
}
