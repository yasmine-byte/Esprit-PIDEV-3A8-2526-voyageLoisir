<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260402215746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE destination (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, pays VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, statut TINYINT DEFAULT NULL, meilleure_saison VARCHAR(50) DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, nb_visites INT DEFAULT NULL, video_path VARCHAR(500) DEFAULT NULL, voyage_id INT DEFAULT NULL, INDEX IDX_3EC63EAA68C9E5AF (voyage_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE image (id INT AUTO_INCREMENT NOT NULL, url_image VARCHAR(255) NOT NULL, destination_id INT DEFAULT NULL, INDEX IDX_C53D045F816C6140 (destination_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transport (id INT AUTO_INCREMENT NOT NULL, type_transport VARCHAR(100) DEFAULT NULL, destination_id INT DEFAULT NULL, INDEX IDX_66AB212E816C6140 (destination_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE voyage (id INT AUTO_INCREMENT NOT NULL, date_depart DATE DEFAULT NULL, date_arrivee DATE DEFAULT NULL, point_depart VARCHAR(100) DEFAULT NULL, point_arrivee VARCHAR(100) DEFAULT NULL, prix DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE destination ADD CONSTRAINT FK_3EC63EAA68C9E5AF FOREIGN KEY (voyage_id) REFERENCES voyage (id)');
        $this->addSql('ALTER TABLE image ADD CONSTRAINT FK_C53D045F816C6140 FOREIGN KEY (destination_id) REFERENCES destination (id)');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT FK_66AB212E816C6140 FOREIGN KEY (destination_id) REFERENCES destination (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE destination DROP FOREIGN KEY FK_3EC63EAA68C9E5AF');
        $this->addSql('ALTER TABLE image DROP FOREIGN KEY FK_C53D045F816C6140');
        $this->addSql('ALTER TABLE transport DROP FOREIGN KEY FK_66AB212E816C6140');
        $this->addSql('DROP TABLE destination');
        $this->addSql('DROP TABLE image');
        $this->addSql('DROP TABLE transport');
        $this->addSql('DROP TABLE voyage');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
