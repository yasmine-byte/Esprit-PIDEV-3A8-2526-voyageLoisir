<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260402233951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE blog (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, contenu LONGTEXT NOT NULL, image_couverture VARCHAR(255) DEFAULT NULL, author_id VARCHAR(100) DEFAULT NULL, status TINYINT DEFAULT NULL, date_creation DATETIME DEFAULT NULL, date_publication DATETIME DEFAULT NULL, extrait LONGTEXT DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, sentiment_score INT DEFAULT NULL, sentiment_emoji VARCHAR(10) DEFAULT NULL, rating_average DOUBLE PRECISION DEFAULT NULL, rating_count INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE blog_rating (id INT AUTO_INCREMENT NOT NULL, user_name VARCHAR(255) NOT NULL, rating INT NOT NULL, review_text LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL, blog_id INT NOT NULL, INDEX IDX_4DC683E3DAE07E97 (blog_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE blog_views (id INT AUTO_INCREMENT NOT NULL, user_identifier VARCHAR(255) DEFAULT NULL, view_date DATETIME DEFAULT NULL, blog_id INT NOT NULL, INDEX IDX_E11FDF4FDAE07E97 (blog_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commentaire (id INT AUTO_INCREMENT NOT NULL, contenu LONGTEXT NOT NULL, date_creation DATETIME DEFAULT NULL, nomuser VARCHAR(100) NOT NULL, img VARCHAR(255) DEFAULT NULL, likes_count INT DEFAULT NULL, blog_id INT NOT NULL, INDEX IDX_67F068BCDAE07E97 (blog_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE blog_rating ADD CONSTRAINT FK_4DC683E3DAE07E97 FOREIGN KEY (blog_id) REFERENCES blog (id)');
        $this->addSql('ALTER TABLE blog_views ADD CONSTRAINT FK_E11FDF4FDAE07E97 FOREIGN KEY (blog_id) REFERENCES blog (id)');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BCDAE07E97 FOREIGN KEY (blog_id) REFERENCES blog (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog_rating DROP FOREIGN KEY FK_4DC683E3DAE07E97');
        $this->addSql('ALTER TABLE blog_views DROP FOREIGN KEY FK_E11FDF4FDAE07E97');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BCDAE07E97');
        $this->addSql('DROP TABLE blog');
        $this->addSql('DROP TABLE blog_rating');
        $this->addSql('DROP TABLE blog_views');
        $this->addSql('DROP TABLE commentaire');
    }
}
