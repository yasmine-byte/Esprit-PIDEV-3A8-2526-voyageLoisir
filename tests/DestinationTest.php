<?php

namespace App\Tests;

use App\Entity\Destination;
use App\Service\DestinationManager;
use PHPUnit\Framework\TestCase;

class DestinationTest extends TestCase
{
    private DestinationManager $manager;

    protected function setUp(): void
    {
        $this->manager = new DestinationManager();
    }

    // ✅ Test 1 : Destination valide
    public function testDestinationValide(): void
    {
        $destination = new Destination();
        $destination->setNom('Paris');
        $destination->setPays('France');
        $destination->setNbVisites(100);
        $destination->setMeilleureSaison('Printemps');

        $this->assertTrue($this->manager->validate($destination));
    }

    // ❌ Test 2 : Règle 1 — Le nom est obligatoire
    public function testNomObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de la destination est obligatoire.');

        $destination = new Destination();
        $destination->setNom('');
        $destination->setPays('France');

        $this->manager->validate($destination);
    }

    // ❌ Test 3 : Règle 2 — Le pays est obligatoire
    public function testPaysObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le pays de la destination est obligatoire.');

        $destination = new Destination();
        $destination->setNom('Paris');
        $destination->setPays('');

        $this->manager->validate($destination);
    }

    // ❌ Test 4 : Règle 3 — Le nombre de visites ne peut pas être négatif
    public function testNbVisitesNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre de visites ne peut pas être négatif.');

        $destination = new Destination();
        $destination->setNom('Paris');
        $destination->setPays('France');
        $destination->setNbVisites(-5);

        $this->manager->validate($destination);
    }

    // ❌ Test 5 : Règle 4 — La saison doit être valide
    public function testSaisonInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La saison doit être : Printemps, Ete, Automne ou Hiver.');

        $destination = new Destination();
        $destination->setNom('Paris');
        $destination->setPays('France');
        $destination->setMeilleureSaison('Ete_Invalide');

        $this->manager->validate($destination);
    }

    // ✅ Test 6 : Saison nulle acceptée (optionnelle)
    public function testSaisonNulleAcceptee(): void
    {
        $destination = new Destination();
        $destination->setNom('Paris');
        $destination->setPays('France');
        $destination->setMeilleureSaison(null);

        $this->assertTrue($this->manager->validate($destination));
    }

    // ✅ Test 7 : Nombre de visites à zéro accepté
    public function testNbVisitesZeroAccepte(): void
    {
        $destination = new Destination();
        $destination->setNom('Tunis');
        $destination->setPays('Tunisie');
        $destination->setNbVisites(0);

        $this->assertTrue($this->manager->validate($destination));
    }
}