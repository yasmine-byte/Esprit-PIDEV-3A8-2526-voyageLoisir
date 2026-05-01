<?php

namespace App\Service;

use App\Entity\Activite;

class ActiviteManager
{
    /**
     * Valide les règles métier d'une Activite.
     *
     * Règle 1 : Le nom est obligatoire
     * Règle 2 : Le nom doit avoir au moins 3 caractères
     * Règle 3 : La description est obligatoire et >= 10 caractères
     * Règle 4 : Le prix doit être positif
     * Règle 5 : La durée doit être positive
     * Règle 6 : Le rating AI doit être positif ou nul
     */
    public function validate(Activite $activite): bool
    {
        // Règle 1 : Le nom est obligatoire
        if (empty(trim($activite->getNom()))) {
            throw new \InvalidArgumentException('Le nom est obligatoire.');
        }

        // Règle 2 : Le nom doit avoir au moins 3 caractères
        if (mb_strlen(trim($activite->getNom())) < 3) {
            throw new \InvalidArgumentException('Le nom doit contenir au moins 3 caractères.');
        }

        // Règle 3 : La description est obligatoire et >= 10 caractères
        if (empty($activite->getDescription())) {
            throw new \InvalidArgumentException('La description est obligatoire.');
        }
        if (mb_strlen(trim($activite->getDescription())) < 10) {
            throw new \InvalidArgumentException('La description doit contenir au moins 10 caractères.');
        }

        // Règle 4 : Le prix doit être positif
        if ($activite->getPrix() !== null && $activite->getPrix() <= 0) {
            throw new \InvalidArgumentException('Le prix doit être supérieur à 0.');
        }

        // Règle 5 : La durée doit être positive
        if ($activite->getDuree() !== null && $activite->getDuree() <= 0) {
            throw new \InvalidArgumentException('La durée doit être supérieure à 0.');
        }

        // Règle 6 : Le rating AI doit être positif ou nul
        if ($activite->getAiRating() !== null && $activite->getAiRating() < 0) {
            throw new \InvalidArgumentException('La note AI doit être positive ou nulle.');
        }

        return true;
    }
}