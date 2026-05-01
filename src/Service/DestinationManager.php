<?php

namespace App\Service;

use App\Entity\Destination;

class DestinationManager
{
    /**
     * Valide les règles métier d'une Destination.
     *
     * Règle 1 : Le nom est obligatoire
     * Règle 2 : Le pays est obligatoire
     * Règle 3 : Le nombre de visites ne peut pas être négatif
     * Règle 4 : La saison doit être valide (Printemps, Ete, Automne, Hiver)
     */
    public function validate(Destination $destination): bool
    {
        // Règle 1 : Le nom est obligatoire
        if (empty($destination->getNom())) {
            throw new \InvalidArgumentException('Le nom de la destination est obligatoire.');
        }

        // Règle 2 : Le pays est obligatoire
        if (empty($destination->getPays())) {
            throw new \InvalidArgumentException('Le pays de la destination est obligatoire.');
        }

        // Règle 3 : Le nombre de visites ne peut pas être négatif
        if ($destination->getNbVisites() !== null && $destination->getNbVisites() < 0) {
            throw new \InvalidArgumentException('Le nombre de visites ne peut pas être négatif.');
        }

        // Règle 4 : La saison doit être valide
        $saisonsValides = ['Printemps', 'Ete', 'Automne', 'Hiver'];
        if ($destination->getMeilleureSaison() !== null
            && !in_array($destination->getMeilleureSaison(), $saisonsValides, true)) {
            throw new \InvalidArgumentException('La saison doit être : Printemps, Ete, Automne ou Hiver.');
        }

        return true;
    }
}