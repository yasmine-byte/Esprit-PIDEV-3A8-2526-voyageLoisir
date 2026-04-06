<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406091000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add created_by_id and reserved_by_id to voyage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE voyage ADD created_by_id INT DEFAULT NULL, ADD reserved_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT FK_3F9D8955B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT FK_3F9D8955BCDB4AF4 FOREIGN KEY (reserved_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3F9D8955B03A8386 ON voyage (created_by_id)');
        $this->addSql('CREATE INDEX IDX_3F9D8955BCDB4AF4 ON voyage (reserved_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY FK_3F9D8955B03A8386');
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY FK_3F9D8955BCDB4AF4');
        $this->addSql('DROP INDEX IDX_3F9D8955B03A8386 ON voyage');
        $this->addSql('DROP INDEX IDX_3F9D8955BCDB4AF4 ON voyage');
        $this->addSql('ALTER TABLE voyage DROP created_by_id, DROP reserved_by_id');
    }
}
