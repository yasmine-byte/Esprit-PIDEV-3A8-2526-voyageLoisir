<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create comment_report table and add Facebook publication columns to blog';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog ADD facebook_post_id VARCHAR(120) DEFAULT NULL, ADD facebook_publish_status VARCHAR(20) DEFAULT NULL, ADD facebook_published_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE TABLE comment_report (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, commentaire_id INT NOT NULL, reason VARCHAR(40) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_3DBDE258A76ED395 (user_id), INDEX IDX_3DBDE258F8697D13 (commentaire_id), UNIQUE INDEX uniq_comment_report_user (commentaire_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE comment_report ADD CONSTRAINT FK_3DBDE258A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment_report ADD CONSTRAINT FK_3DBDE258F8697D13 FOREIGN KEY (commentaire_id) REFERENCES commentaire (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment_report DROP FOREIGN KEY FK_3DBDE258A76ED395');
        $this->addSql('ALTER TABLE comment_report DROP FOREIGN KEY FK_3DBDE258F8697D13');
        $this->addSql('DROP TABLE comment_report');
        $this->addSql('ALTER TABLE blog DROP facebook_post_id, DROP facebook_publish_status, DROP facebook_published_at');
    }
}
