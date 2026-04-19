<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing profile and auth columns to users table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD avatar_name VARCHAR(255) DEFAULT NULL, ADD is_verified TINYINT(1) DEFAULT 0, ADD verification_token VARCHAR(255) DEFAULT NULL, ADD reset_token VARCHAR(255) DEFAULT NULL, ADD reset_token_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP avatar_name, DROP is_verified, DROP verification_token, DROP reset_token, DROP reset_token_expires_at');
    }
}
