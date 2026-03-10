<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260310000009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace newsletter_subscriptions with full newsletter system (subscribers, issues, send_log)';
    }

    public function up(Schema $schema): void
    {
        // Drop the simple table from migration 000008
        $this->addSql('DROP TABLE IF EXISTS newsletter_subscriptions');

        $this->addSql(<<<'SQL'
            CREATE TABLE newsletter_subscribers (
                id              INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
                site_id         INT UNSIGNED   NOT NULL,
                email           VARCHAR(255)   NOT NULL,
                status          VARCHAR(20)    NOT NULL DEFAULT 'pending',
                token           VARCHAR(64)    NOT NULL,
                confirmed_at    DATETIME       DEFAULT NULL,
                unsubscribed_at DATETIME       DEFAULT NULL,
                source          VARCHAR(50)    NOT NULL DEFAULT 'homepage',
                ip_address      VARCHAR(45)    DEFAULT NULL,
                user_agent      TEXT           DEFAULT NULL,
                created_at      DATETIME       NOT NULL,
                updated_at      DATETIME       NOT NULL,
                UNIQUE KEY uniq_site_email (site_id, email),
                UNIQUE KEY uniq_token (token),
                INDEX idx_site_status (site_id, status),
                FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE newsletter_issues (
                id               INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
                site_id          INT UNSIGNED   NOT NULL,
                subject          VARCHAR(255)   NOT NULL,
                preview_text     VARCHAR(255)   DEFAULT NULL,
                content_html     LONGTEXT       NOT NULL,
                content_json     JSON           DEFAULT NULL,
                status           VARCHAR(10)    NOT NULL DEFAULT 'draft',
                generated_by     VARCHAR(10)    NOT NULL DEFAULT 'ai',
                sent_at          DATETIME       DEFAULT NULL,
                recipients_count INT UNSIGNED   NOT NULL DEFAULT 0,
                sent_count       INT UNSIGNED   NOT NULL DEFAULT 0,
                failed_count     INT UNSIGNED   NOT NULL DEFAULT 0,
                created_at       DATETIME       NOT NULL,
                updated_at       DATETIME       NOT NULL,
                INDEX idx_site_status (site_id, status),
                FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE newsletter_send_log (
                id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                issue_id      INT UNSIGNED    NOT NULL,
                subscriber_id INT UNSIGNED    NOT NULL,
                status        VARCHAR(10)     NOT NULL DEFAULT 'queued',
                error_message TEXT            DEFAULT NULL,
                sent_at       DATETIME        DEFAULT NULL,
                UNIQUE KEY uniq_issue_subscriber (issue_id, subscriber_id),
                INDEX idx_issue_status (issue_id, status),
                FOREIGN KEY (issue_id)      REFERENCES newsletter_issues(id) ON DELETE CASCADE,
                FOREIGN KEY (subscriber_id) REFERENCES newsletter_subscribers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS newsletter_send_log');
        $this->addSql('DROP TABLE IF EXISTS newsletter_issues');
        $this->addSql('DROP TABLE IF EXISTS newsletter_subscribers');
    }
}
