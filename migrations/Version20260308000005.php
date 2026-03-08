<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create glossary_terms, exchange_data, affiliate_links tables (Phase 9)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE glossary_terms (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                term VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                short_definition TEXT NOT NULL,
                full_content LONGTEXT NOT NULL,
                faq_json JSON DEFAULT NULL,
                meta_title VARCHAR(255) DEFAULT NULL,
                meta_description VARCHAR(320) DEFAULT NULL,
                first_letter CHAR(1) NOT NULL,
                related_term_slugs JSON DEFAULT NULL,
                status ENUM('draft','published') NOT NULL DEFAULT 'published',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                CONSTRAINT FK_GLOSSARY_SITE FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE,
                UNIQUE INDEX uniq_site_slug (site_id, slug),
                INDEX idx_site_letter (site_id, first_letter, status),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE exchange_data (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                rating DECIMAL(3,1) DEFAULT NULL,
                founded_year SMALLINT DEFAULT NULL,
                headquarters VARCHAR(255) DEFAULT NULL,
                supported_coins INT UNSIGNED DEFAULT NULL,
                trading_fee_maker DECIMAL(5,4) DEFAULT NULL,
                trading_fee_taker DECIMAL(5,4) DEFAULT NULL,
                has_mobile_app TINYINT(1) NOT NULL DEFAULT 1,
                is_regulated TINYINT(1) NOT NULL DEFAULT 0,
                kyc_required TINYINT(1) NOT NULL DEFAULT 1,
                affiliate_url VARCHAR(2048) DEFAULT NULL,
                review_article_id INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                CONSTRAINT FK_EXCHANGE_SITE FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE,
                CONSTRAINT FK_EXCHANGE_ARTICLE FOREIGN KEY (review_article_id) REFERENCES articles (id) ON DELETE SET NULL,
                UNIQUE INDEX uniq_site_slug (site_id, slug),
                INDEX idx_site_rating (site_id, rating),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE affiliate_links (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                partner VARCHAR(100) NOT NULL,
                partner_type ENUM('exchange','wallet','service','course') NOT NULL,
                base_url VARCHAR(2048) NOT NULL,
                utm_source VARCHAR(100) DEFAULT NULL,
                utm_medium VARCHAR(100) DEFAULT 'affiliate',
                utm_campaign VARCHAR(100) DEFAULT NULL,
                display_name VARCHAR(255) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                clicks INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                CONSTRAINT FK_AFFILIATE_SITE FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE,
                UNIQUE INDEX uniq_site_partner (site_id, partner),
                INDEX idx_site_active (site_id, is_active),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE affiliate_links');
        $this->addSql('DROP TABLE exchange_data');
        $this->addSql('DROP TABLE glossary_terms');
    }
}
