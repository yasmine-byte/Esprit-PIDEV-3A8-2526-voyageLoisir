<?php

namespace App\Service;

use App\Entity\Voyage;

class VoyageManager
{
    public function validate(Voyage $voyage): bool
    {
        // date_depart obligatoire
        if ($voyage->getDateDepart() === null) {
            throw new \InvalidArgumentException('La date de départ est obligatoire.');
        }
        // date_depart >= aujourd'hui
        $today = new \DateTime('today');
        if ($voyage->getDateDepart() < $today) {
            throw new \InvalidArgumentException('La date de départ ne peut pas être dans le passé.');
        }

        // date_arrivee obligatoire
        if ($voyage->getDateArrivee() === null) {
            throw new \InvalidArgumentException("La date d'arrivée est obligatoire.");
        }
        // date_arrivee > date_depart
        if ($voyage->getDateArrivee() <= $voyage->getDateDepart()) {
            throw new \InvalidArgumentException("La date d'arrivée doit être après le départ.");
        }

        // point_depart obligatoire
        if (empty($voyage->getPointDepart())) {
            throw new \InvalidArgumentException('Le point de départ est obligatoire.');
        }
        if (strlen($voyage->getPointDepart()) < 2) {
            throw new \InvalidArgumentException('Le point de départ doit contenir au moins 2 caractères.');
        }
        if (strlen($voyage->getPointDepart()) > 100) {
            throw new \InvalidArgumentException('Le point de départ ne peut pas dépasser 100 caractères.');
        }
        if (preg_match('/[0-9]/', $voyage->getPointDepart())) {
            throw new \InvalidArgumentException('Le point de départ ne doit pas contenir de chiffres.');
        }

        // point_arrivee obligatoire
        if (empty($voyage->getPointArrivee())) {
            throw new \InvalidArgumentException("Le point d'arrivée est obligatoire.");
        }
        if (strlen($voyage->getPointArrivee()) < 2) {
            throw new \InvalidArgumentException("Le point d'arrivée doit contenir au moins 2 caractères.");
        }
        if (strlen($voyage->getPointArrivee()) > 100) {
            throw new \InvalidArgumentException("Le point d'arrivée ne peut pas dépasser 100 caractères.");
        }
        if (preg_match('/[0-9]/', $voyage->getPointArrivee())) {
            throw new \InvalidArgumentException("Le point d'arrivée ne doit pas contenir de chiffres.");
        }

        // prix obligatoire
        if ($voyage->getPrix() === null) {
            throw new \InvalidArgumentException('Le prix est obligatoire.');
        }
        if ($voyage->getPrix() < 0) {
            throw new \InvalidArgumentException('Le prix ne peut pas être négatif.');
        }
        if ($voyage->getPrix() > 99999) {
            throw new \InvalidArgumentException('Le prix ne peut pas dépasser 99 999 EUR.');
        }

        return true;
    }
}