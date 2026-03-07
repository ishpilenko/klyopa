<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema: all tables for the multisite platform.
 */
final class Version20260307000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: sites, categories, articles, tags, media, tools, redirects, content_queue, internal_links';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE sites (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                domain VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                vertical ENUM('crypto','finance','gambling','general') NOT NULL,
                theme VARCHAR(50) NOT NULL DEFAULT 'default',
                locale VARCHAR(10) NOT NULL DEFAULT 'en',
                default_meta_title VARCHAR(255) DEFAULT NULL,
                default_meta_description LONGTEXT DEFAULT NULL,
                analytics_id VARCHAR(50) DEFAULT NULL,
                search_console_id VARCHAR(255) DEFAULT NULL,
                settings JSON DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_BC00AA63A7A91E0B (domain),
                INDEX idx_domain (domain),
                INDEX idx_vertical (vertical),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE categories (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                parent_id INT UNSIGNED DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description LONGTEXT DEFAULT NULL,
                meta_title VARCHAR(255) DEFAULT NULL,
                meta_description LONGTEXT DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX uniq_site_slug (site_id, slug),
                INDEX idx_site_active (site_id, is_active),
                INDEX IDX_3AF34668727ACA70 (parent_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_3AF346681A665EF5 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE,
                CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE media (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                filename VARCHAR(255) NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                file_size INT UNSIGNED NOT NULL,
                path VARCHAR(500) NOT NULL,
                alt_text VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_site (site_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_6A2CA10C1A665EF5 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE articles (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                category_id INT UNSIGNED DEFAULT NULL,
                featured_image_id INT UNSIGNED DEFAULT NULL,
                title VARCHAR(500) NOT NULL,
                slug VARCHAR(500) NOT NULL,
                excerpt LONGTEXT DEFAULT NULL,
                content LONGTEXT NOT NULL,
                status ENUM('draft','review','published','archived') NOT NULL DEFAULT 'draft',
                schema_type ENUM('Article','NewsArticle','BlogPosting','HowTo','FAQPage') NOT NULL DEFAULT 'Article',
                meta_title VARCHAR(255) DEFAULT NULL,
                meta_description VARCHAR(320) DEFAULT NULL,
                author_name VARCHAR(255) DEFAULT NULL,
                source_url VARCHAR(2048) DEFAULT NULL,
                is_ai_generated TINYINT(1) NOT NULL DEFAULT 0,
                is_evergreen TINYINT(1) NOT NULL DEFAULT 0,
                word_count INT UNSIGNED NOT NULL DEFAULT 0,
                reading_time_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                published_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                content_updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX uniq_site_slug (site_id, slug),
                INDEX idx_site_status_published (site_id, status, published_at),
                INDEX idx_site_category (site_id, category_id),
                INDEX IDX_23A0E6612469DE2 (category_id),
                INDEX IDX_23A0E66B3E81E99 (featured_image_id),
                FULLTEXT INDEX ft_title_content (title, content),
                PRIMARY KEY(id),
                CONSTRAINT FK_23A0E661A665EF5 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE,
                CONSTRAINT FK_23A0E6612469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
                CONSTRAINT FK_23A0E66B3E81E99 FOREIGN KEY (featured_image_id) REFERENCES media (id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE tags (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL,
                UNIQUE INDEX uniq_site_slug (site_id, slug),
                PRIMARY KEY(id),
                CONSTRAINT FK_6FAB21661A665EF5 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE article_tags (
                article_id INT UNSIGNED NOT NULL,
                tag_id INT UNSIGNED NOT NULL,
                PRIMARY KEY(article_id, tag_id),
                INDEX IDX_919694F97294869C (article_id),
                INDEX IDX_919694F9BAD26311 (tag_id),
                CONSTRAINT FK_919694F97294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE,
                CONSTRAINT FK_919694F9BAD26311 FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE tools (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                type ENUM('calculator','converter','probability','comparison','checker') NOT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description LONGTEXT DEFAULT NULL,
                config JSON NOT NULL,
                meta_title VARCHAR(255) DEFAULT NULL,
                meta_description VARCHAR(320) DEFAULT NULL,
                schema_type VARCHAR(50) DEFAULT 'SoftwareApplication',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX uniq_site_slug (site_id, slug),
                PRIMARY KEY(id),
                CONSTRAINT FK_E7AA5BC51A665EF5 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE redirects (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                source_path VARCHAR(2048) NOT NULL,
                target_path VARCHAR(2048) NOT NULL,
                status_code SMALLINT NOT NULL DEFAULT 301,
                hits INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_site_source (site_id, source_path(191)),
                PRIMARY KEY(id),
                CONSTRAINT FK_E9DB4B7C1A665EF5 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE content_queue (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                target_category_id INT UNSIGNED DEFAULT NULL,
                result_article_id INT UNSIGNED DEFAULT NULL,
                topic VARCHAR(500) NOT NULL,
                keywords JSON DEFAULT NULL,
                target_word_count INT UNSIGNED NOT NULL DEFAULT 1500,
                prompt_template VARCHAR(100) DEFAULT NULL,
                status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
                error_message LONGTEXT DEFAULT NULL,
                priority SMALLINT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                processed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_status_priority (status, priority),
                INDEX IDX_ECB37F16C6E04C6 (target_category_id),
                INDEX IDX_ECB37F162A2B6C77 (result_article_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_ECB37F161A665EF5 FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE,
                CONSTRAINT FK_ECB37F16C6E04C6 FOREIGN KEY (target_category_id) REFERENCES categories (id) ON DELETE SET NULL,
                CONSTRAINT FK_ECB37F162A2B6C77 FOREIGN KEY (result_article_id) REFERENCES articles (id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE internal_links (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                source_article_id INT UNSIGNED NOT NULL,
                target_article_id INT UNSIGNED NOT NULL,
                anchor_text VARCHAR(255) NOT NULL,
                is_auto_generated TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX uniq_source_target (source_article_id, target_article_id),
                INDEX IDX_98BC88F160EA1183 (source_article_id),
                INDEX IDX_98BC88F1C4C7B5B4 (target_article_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_98BC88F160EA1183 FOREIGN KEY (source_article_id) REFERENCES articles (id) ON DELETE CASCADE,
                CONSTRAINT FK_98BC88F1C4C7B5B4 FOREIGN KEY (target_article_id) REFERENCES articles (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
                id BIGINT AUTO_INCREMENT NOT NULL,
                body LONGTEXT NOT NULL,
                headers LONGTEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_75EA56E0FB7336F0 (queue_name),
                INDEX IDX_75EA56E0E3BD61CE (available_at),
                INDEX IDX_75EA56E016BA31DB (delivered_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS internal_links');
        $this->addSql('DROP TABLE IF EXISTS content_queue');
        $this->addSql('DROP TABLE IF EXISTS redirects');
        $this->addSql('DROP TABLE IF EXISTS tools');
        $this->addSql('DROP TABLE IF EXISTS article_tags');
        $this->addSql('DROP TABLE IF EXISTS tags');
        $this->addSql('DROP TABLE IF EXISTS articles');
        $this->addSql('DROP TABLE IF EXISTS media');
        $this->addSql('DROP TABLE IF EXISTS categories');
        $this->addSql('DROP TABLE IF EXISTS sites');
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
    }
}
