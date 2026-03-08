<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 8 — seed tool records for the three new dedicated calculators.
 * crypto.localhost (site_id = 4)
 */
final class Version20260308000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed Phase-8 crypto calculator tool records (mining, tax, gas)';
    }

    public function up(Schema $schema): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $tools = [
            [
                'site_id'      => 4,
                'type'         => 'calculator',
                'name'         => 'Mining Profitability Calculator',
                'slug'         => 'mining-calculator',
                'description'  => 'Calculate Bitcoin, Litecoin, Monero, and Kaspa mining profitability based on your hashrate, power, and electricity cost.',
                'meta_title'   => 'Crypto Mining Calculator — Is Mining Profitable? | CryptoInsider',
                'meta_desc'    => 'Calculate crypto mining profitability. Enter hashrate, power consumption, and electricity cost to see daily and monthly earnings.',
                'schema_type'  => 'SoftwareApplication',
                'config'       => json_encode(['dedicated_route' => true]),
            ],
            [
                'site_id'      => 4,
                'type'         => 'calculator',
                'name'         => 'Crypto Tax Calculator',
                'slug'         => 'crypto-tax-calculator',
                'description'  => 'Estimate your cryptocurrency capital gains tax for US, UK, Germany, and Australia. Updated for 2026.',
                'meta_title'   => 'Crypto Tax Calculator 2026 — Estimate Your Crypto Taxes | CryptoInsider',
                'meta_desc'    => 'Free crypto tax calculator for US, UK, Germany, and Australia. Estimate capital gains tax on crypto profits. Updated 2026.',
                'schema_type'  => 'SoftwareApplication',
                'config'       => json_encode(['dedicated_route' => true]),
            ],
            [
                'site_id'      => 4,
                'type'         => 'calculator',
                'name'         => 'Ethereum Gas Tracker',
                'slug'         => 'gas-tracker',
                'description'  => 'Live Ethereum gas prices in Gwei and USD. Auto-refreshes every 15 seconds. Includes transaction cost estimates.',
                'meta_title'   => 'Ethereum Gas Tracker — Current Gas Fees | CryptoInsider',
                'meta_desc'    => 'Live Ethereum gas prices. See current slow, average, and fast gas fees in Gwei and USD.',
                'schema_type'  => 'SoftwareApplication',
                'config'       => json_encode(['dedicated_route' => true]),
            ],
        ];

        foreach ($tools as $tool) {
            // Skip if already exists (idempotent)
            $existing = $this->connection->fetchOne(
                'SELECT id FROM tools WHERE site_id = ? AND slug = ?',
                [$tool['site_id'], $tool['slug']],
            );

            if ($existing !== false) {
                continue;
            }

            $this->connection->executeStatement(
                'INSERT INTO tools
                    (site_id, type, name, slug, description, meta_title, meta_description, schema_type, config, is_active, created_at, updated_at)
                 VALUES
                    (:site_id, :type, :name, :slug, :description, :meta_title, :meta_description, :schema_type, :config, 1, :created_at, :updated_at)',
                [
                    'site_id'          => $tool['site_id'],
                    'type'             => $tool['type'],
                    'name'             => $tool['name'],
                    'slug'             => $tool['slug'],
                    'description'      => $tool['description'],
                    'meta_title'       => $tool['meta_title'],
                    'meta_description' => $tool['meta_desc'],
                    'schema_type'      => $tool['schema_type'],
                    'config'           => $tool['config'],
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->connection->executeStatement(
            "DELETE FROM tools WHERE site_id = 4 AND slug IN ('mining-calculator','crypto-tax-calculator','gas-tracker')",
        );
    }
}
