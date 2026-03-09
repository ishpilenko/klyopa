<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260309000007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table and seed initial super-admin (email: admin@localhost, password: admin123)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id         INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
                email      VARCHAR(255)   NOT NULL,
                name       VARCHAR(255)   NOT NULL,
                roles      JSON           NOT NULL,
                password   VARCHAR(255)   NOT NULL,
                is_active  TINYINT(1)     NOT NULL DEFAULT 1,
                created_at DATETIME       NOT NULL,
                updated_at DATETIME       NOT NULL,
                UNIQUE KEY uniq_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // Password: admin123 — change via admin panel after first login
        // Hash generated with: password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 13])
        $this->addSql(<<<'SQL'
            INSERT INTO users (email, name, roles, password, is_active, created_at, updated_at)
            VALUES (
                'admin@localhost',
                'Super Admin',
                '["ROLE_SUPER_ADMIN"]',
                '$2y$13$wT9tpzYPvZRtuSiQ2FscN.5Lx8QL7vrpMXMom0yFVaAKZKMjbR3/G',
                1,
                NOW(),
                NOW()
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
