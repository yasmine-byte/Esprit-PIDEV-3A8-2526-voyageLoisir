<?php

namespace App\Service;

use App\Entity\Chambre;

class ChambreManager
{
    public function validate(Chambre $chambre): bool
    {
        if (empty($chambre->getNumero())) {
            throw new \InvalidArgumentException('Le numéro de chambre est obligatoire.');
        }

        $typesValides = ['simple', 'double', 'suite', 'familiale'];
        if (!in_array($chambre->getTypeChambre(), $typesValides)) {
            throw new \InvalidArgumentException('Le type doit être : simple, double, suite ou familiale.');
        }

        if ($chambre->getPrixNuit() === null || $chambre->getPrixNuit() === '') {
            throw new \InvalidArgumentException('Le prix par nuit est obligatoire.');
        }
        if ((float) $chambre->getPrixNuit() <= 0) {
            throw new \InvalidArgumentException('Le prix doit être un nombre positif.');
        }

        if ($chambre->getCapacite() === null) {
            throw new \InvalidArgumentException('La capacité est obligatoire.');
        }
        if ($chambre->getCapacite() <= 0) {
            throw new \InvalidArgumentException('La capacité doit être un nombre positif.');
        }
        if ($chambre->getCapacite() > 20) {
            throw new \InvalidArgumentException('La capacité ne peut pas dépasser 20 personnes.');
        }

        return true;
    }
}