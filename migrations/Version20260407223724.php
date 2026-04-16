<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407223724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY `FK_3B7BB2838A0AGTB6`');
        $this->addSql('DROP INDEX idx_3b7bb2838a0agtb6 ON voyage');
        $this->addSql('CREATE INDEX IDX_3F9D8955816C6140 ON voyage (destination_id)');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT `FK_3B7BB2838A0AGTB6` FOREIGN KEY (destination_id) REFERENCES destination (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY FK_3F9D8955816C6140');
        $this->addSql('DROP INDEX idx_3f9d8955816c6140 ON voyage');
        $this->addSql('CREATE INDEX IDX_3B7BB2838A0AGTB6 ON voyage (destination_id)');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT FK_3F9D8955816C6140 FOREIGN KEY (destination_id) REFERENCES destination (id) ON DELETE SET NULL');
    }
}
