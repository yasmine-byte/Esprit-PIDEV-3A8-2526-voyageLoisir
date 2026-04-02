<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260402231640 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE avis (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, contenu LONGTEXT NOT NULL, nb_etoiles INT NOT NULL, statut VARCHAR(20) NOT NULL, date_avis DATETIME NOT NULL, type_id INT NOT NULL, INDEX IDX_8F91ABF0C54C8C93 (type_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reclamation (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, titre VARCHAR(255) NOT NULL, contenu LONGTEXT NOT NULL, type_feedback VARCHAR(20) NOT NULL, statut VARCHAR(20) NOT NULL, priorite VARCHAR(20) NOT NULL, date_creation DATETIME NOT NULL, type_id INT NOT NULL, avis_id INT DEFAULT NULL, INDEX IDX_CE606404C54C8C93 (type_id), INDEX IDX_CE606404197E709F (avis_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE type_avis (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(50) NOT NULL, description LONGTEXT DEFAULT NULL, date_creation DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT FK_8F91ABF0C54C8C93 FOREIGN KEY (type_id) REFERENCES type_avis (id)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404C54C8C93 FOREIGN KEY (type_id) REFERENCES type_avis (id)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404197E709F FOREIGN KEY (avis_id) REFERENCES avis (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY FK_8F91ABF0C54C8C93');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404C54C8C93');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404197E709F');
        $this->addSql('DROP TABLE avis');
        $this->addSql('DROP TABLE reclamation');
        $this->addSql('DROP TABLE type_avis');
    }
}
