<?php

namespace App\Tests\Service;

use App\Entity\Chambre;
use App\Service\ChambreManager;
use PHPUnit\Framework\TestCase;

class ChambreManagerTest extends TestCase
{
    private ChambreManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ChambreManager();
    }

    public function testChambreValide(): void
    {
        $chambre = new Chambre();
        $chambre->setNumero('101');
        $chambre->setTypeChambre('double');
        $chambre->setPrixNuit('80.00');
        $chambre->setCapacite(2);
        $this->assertTrue($this->manager->validate($chambre));
    }

    public function testNumeroObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le numéro de chambre est obligatoire.');
        $chambre = new Chambre();
        $chambre->setNumero('');
        $chambre->setTypeChambre('simple');
        $chambre->setPrixNuit('50.00');
        $chambre->setCapacite(1);
        $this->manager->validate($chambre);
    }

    public function testTypeInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type doit être : simple, double, suite ou familiale.');
        $chambre = new Chambre();
        $chambre->setNumero('102');
        $chambre->setTypeChambre('penthouse');
        $chambre->setPrixNuit('50.00');
        $chambre->setCapacite(1);
        $this->manager->validate($chambre);
    }

    public function testPrixObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix par nuit est obligatoire.');
        $chambre = new Chambre();
        $chambre->setNumero('103');
        $chambre->setTypeChambre('simple');
        $chambre->setPrixNuit(null);
        $chambre->setCapacite(1);
        $this->manager->validate($chambre);
    }

    public function testPrixNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix doit être un nombre positif.');
        $chambre = new Chambre();
        $chambre->setNumero('104');
        $chambre->setTypeChambre('simple');
        $chambre->setPrixNuit('-30.00');
        $chambre->setCapacite(1);
        $this->manager->validate($chambre);
    }

    public function testCapaciteObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La capacité est obligatoire.');
        $chambre = new Chambre();
        $chambre->setNumero('105');
        $chambre->setTypeChambre('simple');
        $chambre->setPrixNuit('50.00');
        $chambre->setCapacite(null);
        $this->manager->validate($chambre);
    }

    public function testCapaciteTropElevee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La capacité ne peut pas dépasser 20 personnes.');
        $chambre = new Chambre();
        $chambre->setNumero('106');
        $chambre->setTypeChambre('familiale');
        $chambre->setPrixNuit('150.00');
        $chambre->setCapacite(25);
        $this->manager->validate($chambre);
    }

    public function testTousLesTypesValides(): void
    {
        foreach (['simple', 'double', 'suite', 'familiale'] as $type) {
            $chambre = new Chambre();
            $chambre->setNumero('200');
            $chambre->setTypeChambre($type);
            $chambre->setPrixNuit('60.00');
            $chambre->setCapacite(2);
            $this->assertTrue($this->manager->validate($chambre));
        }
    }
}