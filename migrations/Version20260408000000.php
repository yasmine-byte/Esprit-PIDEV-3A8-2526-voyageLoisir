<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Transport belongs to Voyage instead of Destination: replace destination_id with voyage_id on transport';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_66AB212E816C6140 ON transport');
        $this->addSql('ALTER TABLE transport DROP COLUMN destination_id');

        $this->addSql('ALTER TABLE transport ADD voyage_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT FK_66AB212E68C9E5AF FOREIGN KEY (voyage_id) REFERENCES voyage (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_66AB212E68C9E5AF ON transport (voyage_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transport DROP FOREIGN KEY FK_66AB212E68C9E5AF');
        $this->addSql('DROP INDEX IDX_66AB212E68C9E5AF ON transport');
        $this->addSql('ALTER TABLE transport DROP COLUMN voyage_id');

        $this->addSql('ALTER TABLE transport ADD destination_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT FK_66AB212E816C6140 FOREIGN KEY (destination_id) REFERENCES destination (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_66AB212E816C6140 ON transport (destination_id)');
    }
}
