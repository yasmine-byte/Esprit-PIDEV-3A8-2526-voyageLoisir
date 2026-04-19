<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260411214635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE voyage_reservations (voyage_id INT NOT NULL, users_id INT NOT NULL, INDEX IDX_38A6746568C9E5AF (voyage_id), INDEX IDX_38A6746567B3B43D (users_id), PRIMARY KEY (voyage_id, users_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE voyage_reservations ADD CONSTRAINT FK_38A6746568C9E5AF FOREIGN KEY (voyage_id) REFERENCES voyage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE voyage_reservations ADD CONSTRAINT FK_38A6746567B3B43D FOREIGN KEY (users_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY `FK_3F9D8955BCDB4AF4`');
        $this->addSql('DROP INDEX IDX_3F9D8955BCDB4AF4 ON voyage');
        $this->addSql('ALTER TABLE voyage DROP reserved_by_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE voyage_reservations DROP FOREIGN KEY FK_38A6746568C9E5AF');
        $this->addSql('ALTER TABLE voyage_reservations DROP FOREIGN KEY FK_38A6746567B3B43D');
        $this->addSql('DROP TABLE voyage_reservations');
        $this->addSql('ALTER TABLE voyage ADD reserved_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT `FK_3F9D8955BCDB4AF4` FOREIGN KEY (reserved_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3F9D8955BCDB4AF4 ON voyage (reserved_by_id)');
    }
}
