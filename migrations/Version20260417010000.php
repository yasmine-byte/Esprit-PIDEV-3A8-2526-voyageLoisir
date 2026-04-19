<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create comment_reaction table to store one reaction per user and comment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE comment_reaction (id INT AUTO_INCREMENT NOT NULL, commentaire_id INT NOT NULL, user_id INT NOT NULL, reaction_type VARCHAR(20) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_656DD136F8697D13 (commentaire_id), INDEX IDX_656DD136A76ED395 (user_id), UNIQUE INDEX uniq_comment_user_reaction (commentaire_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE comment_reaction ADD CONSTRAINT FK_656DD136F8697D13 FOREIGN KEY (commentaire_id) REFERENCES commentaire (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment_reaction ADD CONSTRAINT FK_656DD136A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE comment_reaction');
    }
}
