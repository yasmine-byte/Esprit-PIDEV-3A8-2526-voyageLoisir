<?php

namespace App\Tests;

use App\Entity\Activite;
use App\Service\ActiviteManager;
use PHPUnit\Framework\TestCase;

class ActiviteManagerTest extends TestCase
{
    private ActiviteManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ActiviteManager();
    }

    // ✅ Test 1 : Activite valide complète
    public function testActiviteValide(): void
    {
        $activite = new Activite();
        $activite->setNom('Plongée sous-marine');
        $activite->setDescription('Découverte des fonds marins avec un guide certifié.');
        $activite->setPrix(75.0);
        $activite->setDuree(120);
        $activite->setAiRating(4.5);

        $this->assertTrue($this->manager->validate($activite));
    }

    // ✅ Test 2 : Nom vide → exception
    public function testNomObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est obligatoire.');

        $activite = new Activite();
        $activite->setNom('');
        $activite->setDescription('Description valide de activite.');

        $this->manager->validate($activite);
    }

    // ✅ Test 3 : Nom trop court → exception
    public function testNomTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom doit contenir au moins 3 caractères.');

        $activite = new Activite();
        $activite->setNom('Ab');
        $activite->setDescription('Description valide de activite.');

        $this->manager->validate($activite);
    }

    // ✅ Test 4 : Description vide → exception
    public function testDescriptionObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description est obligatoire.');

        $activite = new Activite();
        $activite->setNom('Surf');
        $activite->setDescription(null);

        $this->manager->validate($activite);
    }

    // ✅ Test 5 : Description trop courte → exception
    public function testDescriptionTropCourte(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description doit contenir au moins 10 caractères.');

        $activite = new Activite();
        $activite->setNom('Surf');
        $activite->setDescription('Court');

        $this->manager->validate($activite);
    }

    // ✅ Test 6 : Prix négatif → exception
    public function testPrixNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix doit être supérieur à 0.');

        $activite = new Activite();
        $activite->setNom('Surf');
        $activite->setDescription('Description valide de activite.');
        $activite->setPrix(-10.0);

        $this->manager->validate($activite);
    }

    // ✅ Test 7 : Prix zéro → exception
    public function testPrixZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix doit être supérieur à 0.');

        $activite = new Activite();
        $activite->setNom('Surf');
        $activite->setDescription('Description valide de activite.');
        $activite->setPrix(0.0);

        $this->manager->validate($activite);
    }

    // ✅ Test 8 : Durée négative → exception
    public function testDureeNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La durée doit être supérieure à 0.');

        $activite = new Activite();
        $activite->setNom('Surf');
        $activite->setDescription('Description valide de activite.');
        $activite->setPrix(50.0);
        $activite->setDuree(-30);

        $this->manager->validate($activite);
    }

    // ✅ Test 9 : Rating AI négatif → exception
    public function testAiRatingNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La note AI doit être positive ou nulle.');

        $activite = new Activite();
        $activite->setNom('Surf');
        $activite->setDescription('Description valide de activite.');
        $activite->setPrix(50.0);
        $activite->setDuree(60);
        $activite->setAiRating(-1.0);

        $this->manager->validate($activite);
    }

    // ✅ Test 10 : Prix et durée null acceptés
    public function testPrixEtDureeNullAcceptes(): void
    {
        $activite = new Activite();
        $activite->setNom('Surf');
        $activite->setDescription('Description valide de activite.');
        $activite->setPrix(null);
        $activite->setDuree(null);

        $this->assertTrue($this->manager->validate($activite));
    }
}