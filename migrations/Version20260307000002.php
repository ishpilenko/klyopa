<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260307000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add api_tokens table for Bearer-token authentication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE api_tokens (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                token VARCHAR(64) NOT NULL,
                name VARCHAR(255) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_token (token),
                INDEX idx_site (site_id),
                UNIQUE INDEX uniq_token (token),
                CONSTRAINT fk_api_tokens_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE api_tokens');
    }
}
