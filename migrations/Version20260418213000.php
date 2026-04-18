<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_favorite table for persistent vision board';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_favorite (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, blog_id INT NOT NULL, created_at DATETIME DEFAULT NULL, INDEX IDX_59A9B10EA76ED395 (user_id), INDEX IDX_59A9B10EDA5E37F9 (blog_id), UNIQUE INDEX uniq_user_favorite (user_id, blog_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_favorite ADD CONSTRAINT FK_59A9B10EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_favorite ADD CONSTRAINT FK_59A9B10EDA5E37F9 FOREIGN KEY (blog_id) REFERENCES blog (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_favorite');
    }
}

