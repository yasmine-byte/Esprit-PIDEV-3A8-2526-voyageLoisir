<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert blog status to string values for brouillon/publie workflow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog MODIFY status VARCHAR(50) DEFAULT NULL');
        $this->addSql("UPDATE blog SET status = 'publie' WHERE status = '1'");
        $this->addSql("UPDATE blog SET status = 'brouillon' WHERE status = '0' OR status IS NULL OR status = ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE blog SET status = '1' WHERE status = 'publie'");
        $this->addSql("UPDATE blog SET status = '0' WHERE status = 'brouillon' OR status IS NULL OR status = ''");
        $this->addSql('ALTER TABLE blog MODIFY status TINYINT DEFAULT NULL');
    }
}
