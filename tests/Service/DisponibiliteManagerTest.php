<?php

namespace App\Tests\Service;

use App\Entity\Disponibilite;
use App\Service\DisponibiliteManager;
use PHPUnit\Framework\TestCase;

class DisponibiliteManagerTest extends TestCase
{
    private DisponibiliteManager $manager;

    protected function setUp(): void
    {
        $this->manager = new DisponibiliteManager();
    }

    public function testDisponibiliteValide(): void
    {
        $dispo = new Disponibilite();
        $dispo->setDateDebut(new \DateTime('+1 day'));
        $dispo->setDateFin(new \DateTime('+5 days'));
        $dispo->setDisponible(true);
        $this->assertTrue($this->manager->validate($dispo));
    }

    public function testDateDebutObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de début est obligatoire.');
        $dispo = new Disponibilite();
        $dispo->setDateDebut(null);
        $dispo->setDateFin(new \DateTime('+5 days'));
        $this->manager->validate($dispo);
    }

    public function testDateFinObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de fin est obligatoire.');
        $dispo = new Disponibilite();
        $dispo->setDateDebut(new \DateTime('+1 day'));
        $dispo->setDateFin(null);
        $this->manager->validate($dispo);
    }

    public function testDateFinAvantDateDebut(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de fin doit être après la date de début.');
        $dispo = new Disponibilite();
        $dispo->setDateDebut(new \DateTime('+5 days'));
        $dispo->setDateFin(new \DateTime('+1 day'));
        $this->manager->validate($dispo);
    }

    public function testDatesIdentiques(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de fin doit être après la date de début.');
        $date = new \DateTime('+3 days');
        $dispo = new Disponibilite();
        $dispo->setDateDebut($date);
        $dispo->setDateFin(clone $date);
        $this->manager->validate($dispo);
    }

    public function testDateDebutDansLePasse(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de début doit être aujourd\'hui ou dans le futur.');
        $dispo = new Disponibilite();
        $dispo->setDateDebut(new \DateTime('-1 day'));
        $dispo->setDateFin(new \DateTime('+5 days'));
        $this->manager->validate($dispo);
    }

    public function testDateDebutAujourdhui(): void
    {
        $dispo = new Disponibilite();
        $dispo->setDateDebut(new \DateTime('today'));
        $dispo->setDateFin(new \DateTime('+3 days'));
        $this->assertTrue($this->manager->validate($dispo));
    }
}