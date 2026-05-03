<?php

namespace App\Tests\Service;

use App\Entity\Transport;
use App\Entity\Voyage;
use App\Service\TransportManager;
use PHPUnit\Framework\TestCase;

class TransportManagerTest extends TestCase
{
    private function makeValidTransport(): Transport
    {
        $voyage = new Voyage();
        $voyage->setDateDepart(new \DateTime('+1 day'));
        $voyage->setDateArrivee(new \DateTime('+5 days'));
        $voyage->setPointDepart('Tunis');
        $voyage->setPointArrivee('Paris');
        $voyage->setPrix(300.0);

        $t = new Transport();
        $t->setTypeTransport('Avion');
        $t->setVoyage($voyage);
        return $t;
    }

    // ✅ Cas valide
    public function testTransportValide(): void
    {
        $this->assertTrue((new TransportManager())->validate($this->makeValidTransport()));
    }

    // ❌ Type transport vide
    public function testTypeTransportVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de transport est obligatoire.');
        $t = $this->makeValidTransport();
        $t->setTypeTransport('');
        (new TransportManager())->validate($t);
    }

    // ❌ Type transport null
    public function testTypeTransportNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de transport est obligatoire.');
        $t = $this->makeValidTransport();
        $t->setTypeTransport(null);
        (new TransportManager())->validate($t);
    }

    // ❌ Type transport invalide
    public function testTypeTransportInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Type de transport invalide.');
        $t = $this->makeValidTransport();
        $t->setTypeTransport('Vélo');
        (new TransportManager())->validate($t);
    }

    // ✅ Tous les types valides
    public function testTousLesTypesValides(): void
    {
        $manager = new TransportManager();
        foreach (['Avion', 'Bus', 'Voiture', 'Train'] as $type) {
            $t = $this->makeValidTransport();
            $t->setTypeTransport($type);
            $this->assertTrue($manager->validate($t));
        }
    }

    // ❌ Voyage null
    public function testVoyageNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le voyage est obligatoire.');
        $t = $this->makeValidTransport();
        $t->setVoyage(null);
        (new TransportManager())->validate($t);
    }
}