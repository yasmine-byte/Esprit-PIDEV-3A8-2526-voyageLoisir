<?php

namespace App\Tests\Service;

use App\Entity\Reservation;
use App\Service\ReservationManager;
use PHPUnit\Framework\TestCase;

class ReservationManagerTest extends TestCase
{
    private ReservationManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ReservationManager();
    }

    public function testReservationValide(): void
    {
        $reservation = new Reservation();
        $reservation->setClientNom('Ahmed Ben Ali');
        $reservation->setClientEmail('ahmed@gmail.com');
        $reservation->setDateDebut(new \DateTimeImmutable('2025-06-01'));
        $reservation->setDateFin(new \DateTimeImmutable('2025-06-05'));
        $reservation->setNbNuits(4);
        $this->assertTrue($this->manager->validate($reservation));
    }

    public function testNomObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom du client est obligatoire.');
        $reservation = new Reservation();
        $reservation->setClientNom('');
        $reservation->setClientEmail('ahmed@gmail.com');
        $reservation->setDateDebut(new \DateTimeImmutable('2025-06-01'));
        $reservation->setDateFin(new \DateTimeImmutable('2025-06-05'));
        $this->manager->validate($reservation);
    }

    public function testEmailInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'email du client est invalide.');
        $reservation = new Reservation();
        $reservation->setClientNom('Ahmed Ben Ali');
        $reservation->setClientEmail('email_invalide');
        $reservation->setDateDebut(new \DateTimeImmutable('2025-06-01'));
        $reservation->setDateFin(new \DateTimeImmutable('2025-06-05'));
        $this->manager->validate($reservation);
    }

    public function testEmailObligatoire(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'email du client est obligatoire.');
        $reservation = new Reservation();
        $reservation->setClientNom('Ahmed Ben Ali');
        $reservation->setClientEmail('');
        $reservation->setDateDebut(new \DateTimeImmutable('2025-06-01'));
        $reservation->setDateFin(new \DateTimeImmutable('2025-06-05'));
        $this->manager->validate($reservation);
    }

    public function testDateFinAvantDateDebut(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de fin doit être après la date de début.');
        $reservation = new Reservation();
        $reservation->setClientNom('Ahmed Ben Ali');
        $reservation->setClientEmail('ahmed@gmail.com');
        $reservation->setDateDebut(new \DateTimeImmutable('2025-06-10'));
        $reservation->setDateFin(new \DateTimeImmutable('2025-06-05'));
        $this->manager->validate($reservation);
    }

    public function testDatesIdentiques(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de fin doit être après la date de début.');
        $reservation = new Reservation();
        $reservation->setClientNom('Ahmed Ben Ali');
        $reservation->setClientEmail('ahmed@gmail.com');
        $reservation->setDateDebut(new \DateTimeImmutable('2025-06-05'));
        $reservation->setDateFin(new \DateTimeImmutable('2025-06-05'));
        $this->manager->validate($reservation);
    }

    public function testNbNuitsNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre de nuits doit être positif.');
        $reservation = new Reservation();
        $reservation->setClientNom('Ahmed Ben Ali');
        $reservation->setClientEmail('ahmed@gmail.com');
        $reservation->setDateDebut(new \DateTimeImmutable('2025-06-01'));
        $reservation->setDateFin(new \DateTimeImmutable('2025-06-05'));
        $reservation->setNbNuits(-2);
        $this->manager->validate($reservation);
    }
}