<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store FCM token on reservation records for hebergement notifications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation ADD fcm_token VARCHAR(1024) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP fcm_token');
    }
}
