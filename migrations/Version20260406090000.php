<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename activite.decription to description';
    }

    public function up(Schema $schema): void
    {
        // colonne deja nommee description dans la migration initiale
    }

    public function down(Schema $schema): void
    {
        // rien a faire
    }
}