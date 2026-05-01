<?php

namespace App\Service;

use App\Entity\Hebergement;

class HebergementManager
{
    /**
     * Valide les règles métier d'un Hebergement.
     *
     * Règle 1 : La description est obligatoire
     * Règle 2 : Le prix doit être positif
     * Règle 3 : La description doit contenir au moins 10 caractères
     * Règle 4 : Le prix ne peut pas dépasser 99999
     */
    public function validate(Hebergement $hebergement): bool
    {
        // Règle 1 : La description est obligatoire
        if (empty($hebergement->getDescription())) {
            throw new \InvalidArgumentException('La description est obligatoire.');
        }

        // Règle 3 : La description doit contenir au moins 10 caractères
        if (strlen($hebergement->getDescription()) < 10) {
            throw new \InvalidArgumentException('La description doit contenir au moins 10 caractères.');
        }

        // Règle 2 : Le prix est obligatoire et doit être positif
        if ($hebergement->getPrix() === null || $hebergement->getPrix() === '') {
            throw new \InvalidArgumentException('Le prix est obligatoire.');
        }

        if ((float) $hebergement->getPrix() <= 0) {
            throw new \InvalidArgumentException('Le prix doit être un nombre positif.');
        }

        // Règle 4 : Le prix ne peut pas dépasser 99999
        if ((float) $hebergement->getPrix() > 99999) {
            throw new \InvalidArgumentException('Le prix ne peut pas dépasser 99999.');
        }

        return true;
    }
}