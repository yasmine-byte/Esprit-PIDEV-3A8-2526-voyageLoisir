<?php

namespace App\Service;

use App\Entity\Reservation;

class ReservationManager
{
    public function validate(Reservation $reservation): bool
    {
        if (empty($reservation->getClientNom())) {
            throw new \InvalidArgumentException('Le nom du client est obligatoire.');
        }

        if (empty($reservation->getClientEmail())) {
            throw new \InvalidArgumentException('L\'email du client est obligatoire.');
        }
        if (!filter_var($reservation->getClientEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('L\'email du client est invalide.');
        }

        if ($reservation->getDateDebut() === null) {
            throw new \InvalidArgumentException('La date de début est obligatoire.');
        }
        if ($reservation->getDateFin() === null) {
            throw new \InvalidArgumentException('La date de fin est obligatoire.');
        }
        if ($reservation->getDateFin() <= $reservation->getDateDebut()) {
            throw new \InvalidArgumentException('La date de fin doit être après la date de début.');
        }

        if ($reservation->getNbNuits() !== null && $reservation->getNbNuits() <= 0) {
            throw new \InvalidArgumentException('Le nombre de nuits doit être positif.');
        }

        return true;
    }
}