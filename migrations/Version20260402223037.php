<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260402223037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chambre (id INT AUTO_INCREMENT NOT NULL, numero VARCHAR(10) DEFAULT NULL, type_chambre VARCHAR(50) DEFAULT NULL, prix_nuit NUMERIC(10, 2) DEFAULT NULL, capacite INT DEFAULT NULL, equipements LONGTEXT DEFAULT NULL, hebergement_id INT NOT NULL, INDEX IDX_C509E4FF23BB0F66 (hebergement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE disponibilite (id INT AUTO_INCREMENT NOT NULL, date_debut DATE DEFAULT NULL, date_fin DATE DEFAULT NULL, disponible TINYINT DEFAULT NULL, hebergement_id INT DEFAULT NULL, INDEX IDX_2CBACE2F23BB0F66 (hebergement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE hebergement (id INT AUTO_INCREMENT NOT NULL, description LONGTEXT DEFAULT NULL, prix NUMERIC(10, 2) DEFAULT NULL, image_path VARCHAR(255) DEFAULT NULL, type_id INT DEFAULT NULL, INDEX IDX_4852DD9CC54C8C93 (type_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, client_nom VARCHAR(100) DEFAULT NULL, client_tel VARCHAR(20) DEFAULT NULL, client_email VARCHAR(150) DEFAULT NULL, date_debut DATE DEFAULT NULL, date_fin DATE DEFAULT NULL, nb_nuits INT DEFAULT NULL, total NUMERIC(10, 2) DEFAULT NULL, statut VARCHAR(50) DEFAULT NULL, created_at DATETIME DEFAULT NULL, hebergement_id INT DEFAULT NULL, INDEX IDX_42C8495523BB0F66 (hebergement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE type (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE chambre ADD CONSTRAINT FK_C509E4FF23BB0F66 FOREIGN KEY (hebergement_id) REFERENCES hebergement (id)');
        $this->addSql('ALTER TABLE disponibilite ADD CONSTRAINT FK_2CBACE2F23BB0F66 FOREIGN KEY (hebergement_id) REFERENCES hebergement (id)');
        $this->addSql('ALTER TABLE hebergement ADD CONSTRAINT FK_4852DD9CC54C8C93 FOREIGN KEY (type_id) REFERENCES type (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495523BB0F66 FOREIGN KEY (hebergement_id) REFERENCES hebergement (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chambre DROP FOREIGN KEY FK_C509E4FF23BB0F66');
        $this->addSql('ALTER TABLE disponibilite DROP FOREIGN KEY FK_2CBACE2F23BB0F66');
        $this->addSql('ALTER TABLE hebergement DROP FOREIGN KEY FK_4852DD9CC54C8C93');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495523BB0F66');
        $this->addSql('DROP TABLE chambre');
        $this->addSql('DROP TABLE disponibilite');
        $this->addSql('DROP TABLE hebergement');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE type');
    }
}
