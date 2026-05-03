<?php

namespace App\Tests\Service;

use App\Entity\Voyage;
use App\Service\VoyageManager;
use PHPUnit\Framework\TestCase;

class VoyageManagerTest extends TestCase
{
    private function makeValidVoyage(): Voyage
    {
        $v = new Voyage();
        $v->setDateDepart(new \DateTime('+1 day'));
        $v->setDateArrivee(new \DateTime('+5 days'));
        $v->setPointDepart('Tunis');
        $v->setPointArrivee('Paris');
        $v->setPrix(500.0);
        return $v;
    }

    // ✅ Cas valide
    public function testVoyageValide(): void
    {
        $this->assertTrue((new VoyageManager())->validate($this->makeValidVoyage()));
    }

    // ❌ Date départ null
    public function testDateDepartNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de départ est obligatoire.');
        $v = $this->makeValidVoyage();
        $v->setDateDepart(null);
        (new VoyageManager())->validate($v);
    }

    // ❌ Date départ dans le passé
    public function testDateDepartDansLePasse(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dans le passé');
        $v = $this->makeValidVoyage();
        $v->setDateDepart(new \DateTime('-1 day'));
        (new VoyageManager())->validate($v);
    }

    // ❌ Date arrivée null
    public function testDateArriveeNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("La date d'arrivée est obligatoire.");
        $v = $this->makeValidVoyage();
        $v->setDateArrivee(null);
        (new VoyageManager())->validate($v);
    }

    // ❌ Date arrivée avant départ
    public function testDateArriveeAvantDepart(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("après le départ");
        $v = $this->makeValidVoyage();
        $v->setDateDepart(new \DateTime('+5 days'));
        $v->setDateArrivee(new \DateTime('+2 days'));
        (new VoyageManager())->validate($v);
    }

    // ❌ Date arrivée égale à départ
    public function testDateArriveeEgaleDepart(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $v = $this->makeValidVoyage();
        $date = new \DateTime('+3 days');
        $v->setDateDepart($date);
        $v->setDateArrivee($date);
        (new VoyageManager())->validate($v);
    }

    // ❌ Point départ vide
    public function testPointDepartVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le point de départ est obligatoire.');
        $v = $this->makeValidVoyage();
        $v->setPointDepart('');
        (new VoyageManager())->validate($v);
    }

    // ❌ Point départ trop court
    public function testPointDepartTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('au moins 2 caractères');
        $v = $this->makeValidVoyage();
        $v->setPointDepart('T');
        (new VoyageManager())->validate($v);
    }

    // ❌ Point départ trop long
    public function testPointDepartTropLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('100 caractères');
        $v = $this->makeValidVoyage();
        $v->setPointDepart(str_repeat('A', 101));
        (new VoyageManager())->validate($v);
    }

    // ❌ Point départ avec chiffres
    public function testPointDepartAvecChiffres(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('chiffres');
        $v = $this->makeValidVoyage();
        $v->setPointDepart('Tunis123');
        (new VoyageManager())->validate($v);
    }

    // ❌ Point arrivée vide
    public function testPointArriveeVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le point d'arrivée est obligatoire.");
        $v = $this->makeValidVoyage();
        $v->setPointArrivee('');
        (new VoyageManager())->validate($v);
    }

    // ❌ Point arrivée trop court
    public function testPointArriveeTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $v = $this->makeValidVoyage();
        $v->setPointArrivee('P');
        (new VoyageManager())->validate($v);
    }

    // ❌ Point arrivée avec chiffres
    public function testPointArriveeAvecChiffres(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $v = $this->makeValidVoyage();
        $v->setPointArrivee('Paris9');
        (new VoyageManager())->validate($v);
    }

    // ❌ Prix null
    public function testPrixNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix est obligatoire.');
        $v = $this->makeValidVoyage();
        $v->setPrix(null);
        (new VoyageManager())->validate($v);
    }

    // ❌ Prix négatif
    public function testPrixNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('négatif');
        $v = $this->makeValidVoyage();
        $v->setPrix(-10.0);
        (new VoyageManager())->validate($v);
    }

    // ✅ Prix = 0 (valide)
    public function testPrixZero(): void
    {
        $v = $this->makeValidVoyage();
        $v->setPrix(0.0);
        $this->assertTrue((new VoyageManager())->validate($v));
    }

    // ❌ Prix trop élevé (> 99999)
    public function testPrixTropEleve(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('99 999');
        $v = $this->makeValidVoyage();
        $v->setPrix(100000.0);
        (new VoyageManager())->validate($v);
    }

    // ✅ Prix max valide (99999)
    public function testPrixMaxValide(): void
    {
        $v = $this->makeValidVoyage();
        $v->setPrix(99999.0);
        $this->assertTrue((new VoyageManager())->validate($v));
    }
}