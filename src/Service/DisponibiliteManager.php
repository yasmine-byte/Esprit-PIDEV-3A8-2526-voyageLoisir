<?php

namespace App\Service;

use App\Entity\Disponibilite;

class DisponibiliteManager
{
    public function validate(Disponibilite $disponibilite): bool
    {
        if ($disponibilite->getDateDebut() === null) {
            throw new \InvalidArgumentException('La date de début est obligatoire.');
        }

        if ($disponibilite->getDateFin() === null) {
            throw new \InvalidArgumentException('La date de fin est obligatoire.');
        }

        if ($disponibilite->getDateFin() <= $disponibilite->getDateDebut()) {
            throw new \InvalidArgumentException('La date de fin doit être après la date de début.');
        }

        $today = new \DateTime('today');
        if ($disponibilite->getDateDebut() < $today) {
            throw new \InvalidArgumentException('La date de début doit être aujourd\'hui ou dans le futur.');
        }

        return true;
    }
}