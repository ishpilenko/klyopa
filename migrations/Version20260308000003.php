<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create coin_pages table for programmatic SEO price pages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE coin_pages (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                coin_gecko_id VARCHAR(100) NOT NULL,
                symbol VARCHAR(20) NOT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description LONGTEXT DEFAULT NULL,
                faq_json JSON DEFAULT NULL,
                image_url VARCHAR(500) DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                CONSTRAINT FK_COIN_PAGES_SITE FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE,
                UNIQUE INDEX uniq_site_slug (site_id, slug),
                UNIQUE INDEX uniq_site_coingecko (site_id, coin_gecko_id),
                INDEX idx_site_active (site_id, is_active),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE coin_pages');
    }
}
