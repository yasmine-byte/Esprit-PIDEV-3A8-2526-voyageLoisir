<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Invert Voyage<->Destination relation: voyage now belongs to destination (destination_id on voyage)';
    }

    public function up(Schema $schema): void
    {
        // Drop old FK and voyage_id column from destination (if exists)
        $this->addSql('SET @sql = (SELECT IF(
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = "destination"
             AND CONSTRAINT_NAME = "FK_3EC63EAA68C9E5AF") > 0,
            "ALTER TABLE destination DROP FOREIGN KEY FK_3EC63EAA68C9E5AF",
            "SELECT 1"
        )); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;');

        $this->addSql('SET @sql = (SELECT IF(
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = "destination"
             AND COLUMN_NAME = "voyage_id") > 0,
            "ALTER TABLE destination DROP COLUMN voyage_id",
            "SELECT 1"
        )); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;');

        // Add destination_id FK to voyage
        $this->addSql('ALTER TABLE voyage ADD destination_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT FK_3B7BB2838A0AGTB6 FOREIGN KEY (destination_id) REFERENCES destination (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3B7BB2838A0AGTB6 ON voyage (destination_id)');
    }

    public function down(Schema $schema): void
    {
        // Restore voyage_id on destination
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY FK_3B7BB2838A0AGTB6');
        $this->addSql('DROP INDEX IDX_3B7BB2838A0AGTB6 ON voyage');
        $this->addSql('ALTER TABLE voyage DROP COLUMN destination_id');

        $this->addSql('ALTER TABLE destination ADD voyage_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE destination ADD CONSTRAINT FK_3EC63EAA68C9E5AF FOREIGN KEY (voyage_id) REFERENCES voyage (id)');
        $this->addSql('CREATE INDEX IDX_3EC63EAA68C9E5AF ON destination (voyage_id)');
    }
}
