<?php

namespace App\Tests\Service;
use App\Entity\Hebergement;
use App\Service\HebergementManager;
use PHPUnit\Framework\TestCase;

class HebergementManagerTest extends TestCase
{
    private HebergementManager $manager;

    protected function setUp(): void
    {
        $this->manager = new HebergementManager();
    }

    // ✅ Test 1 : Hebergement valide
    public function testHebergementValide(): void
    {
        $hebergement = new Hebergement();
        $hebergement->setDescription('Bel appartement au centre ville avec vue mer');
        $hebergement->setPrix('150.00');

        $this->assertTrue($this->manager->validate($hebergement));
    }

    // ❌ Test 2 : Règle 1 — Description obligatoire
    public function testDescriptionObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description est obligatoire.');

        $hebergement = new Hebergement();
        $hebergement->setDescription('');
        $hebergement->setPrix('100.00');

        $this->manager->validate($hebergement);
    }

    // ❌ Test 3 : Règle 3 — Description trop courte
    public function testDescriptionTropCourte(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description doit contenir au moins 10 caractères.');

        $hebergement = new Hebergement();
        $hebergement->setDescription('Court');
        $hebergement->setPrix('100.00');

        $this->manager->validate($hebergement);
    }

    // ❌ Test 4 : Règle 2 — Prix obligatoire
    public function testPrixObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix est obligatoire.');

        $hebergement = new Hebergement();
        $hebergement->setDescription('Bel appartement au centre ville');
        $hebergement->setPrix(null);

        $this->manager->validate($hebergement);
    }

    // ❌ Test 5 : Règle 2 — Prix négatif
    public function testPrixNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix doit être un nombre positif.');

        $hebergement = new Hebergement();
        $hebergement->setDescription('Bel appartement au centre ville');
        $hebergement->setPrix('-50.00');

        $this->manager->validate($hebergement);
    }

    // ❌ Test 6 : Règle 2 — Prix égal à zéro
    public function testPrixZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix doit être un nombre positif.');

        $hebergement = new Hebergement();
        $hebergement->setDescription('Bel appartement au centre ville');
        $hebergement->setPrix('0');

        $this->manager->validate($hebergement);
    }

    // ❌ Test 7 : Règle 4 — Prix trop élevé
    public function testPrixTropEleve(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix ne peut pas dépasser 99999.');

        $hebergement = new Hebergement();
        $hebergement->setDescription('Bel appartement au centre ville');
        $hebergement->setPrix('100000.00');

        $this->manager->validate($hebergement);
    }

    // ✅ Test 8 : Prix minimum accepté
    public function testPrixMinimumAccepte(): void
    {
        $hebergement = new Hebergement();
        $hebergement->setDescription('Bel appartement au centre ville');
        $hebergement->setPrix('0.01');

        $this->assertTrue($this->manager->validate($hebergement));
    }
}