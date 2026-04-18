<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418202949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE voyage_hebergement (voyage_id INT NOT NULL, hebergement_id INT NOT NULL, INDEX IDX_7B46453E68C9E5AF (voyage_id), INDEX IDX_7B46453E23BB0F66 (hebergement_id), PRIMARY KEY (voyage_id, hebergement_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE voyage_hebergement ADD CONSTRAINT FK_7B46453E68C9E5AF FOREIGN KEY (voyage_id) REFERENCES voyage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE voyage_hebergement ADD CONSTRAINT FK_7B46453E23BB0F66 FOREIGN KEY (hebergement_id) REFERENCES hebergement (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE voyage_hebergement DROP FOREIGN KEY FK_7B46453E68C9E5AF');
        $this->addSql('ALTER TABLE voyage_hebergement DROP FOREIGN KEY FK_7B46453E23BB0F66');
        $this->addSql('DROP TABLE voyage_hebergement');
    }
}
