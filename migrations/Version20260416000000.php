<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-comment reaction counts for blog comments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE commentaire ADD reaction_counts LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commentaire DROP reaction_counts');
    }
}
