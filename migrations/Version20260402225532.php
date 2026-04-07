<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260402225532 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activite (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(50) DEFAULT NULL, prix DOUBLE PRECISION DEFAULT NULL, duree INT DEFAULT NULL, lieu VARCHAR(100) DEFAULT NULL, image_url VARCHAR(255) DEFAULT NULL, ai_rating DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation_activite (id INT AUTO_INCREMENT NOT NULL, date_reservation DATE NOT NULL, nombre_personnes INT NOT NULL, statut VARCHAR(30) NOT NULL, total DOUBLE PRECISION NOT NULL, activite_id INT DEFAULT NULL, INDEX IDX_25C0B7019B0F88B1 (activite_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reservation_activite ADD CONSTRAINT FK_25C0B7019B0F88B1 FOREIGN KEY (activite_id) REFERENCES activite (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_activite DROP FOREIGN KEY FK_25C0B7019B0F88B1');
        $this->addSql('DROP TABLE activite');
        $this->addSql('DROP TABLE reservation_activite');
    }
}
