<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260310000008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create newsletter_subscriptions table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE newsletter_subscriptions (
                id         INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
                site_id    INT UNSIGNED   NOT NULL,
                email      VARCHAR(255)   NOT NULL,
                status     VARCHAR(20)    NOT NULL DEFAULT 'active',
                ip_address VARCHAR(45)    DEFAULT NULL,
                created_at DATETIME       NOT NULL,
                UNIQUE KEY uniq_site_email (site_id, email),
                INDEX idx_site_status (site_id, status),
                FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE newsletter_subscriptions');
    }
}
