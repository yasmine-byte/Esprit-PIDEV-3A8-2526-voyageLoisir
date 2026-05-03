<?php

namespace App\Service;

use App\Entity\Transport;

class TransportManager
{
    public function validate(Transport $transport): bool
    {
        // type_transport obligatoire
        if (empty($transport->getTypeTransport())) {
            throw new \InvalidArgumentException('Le type de transport est obligatoire.');
        }

        // type_transport : valeur parmi les choix valides
        $types = ['Avion', 'Bus', 'Voiture', 'Train'];
        if (!in_array($transport->getTypeTransport(), $types)) {
            throw new \InvalidArgumentException('Type de transport invalide.');
        }

        // voyage obligatoire
        if ($transport->getVoyage() === null) {
            throw new \InvalidArgumentException('Le voyage est obligatoire.');
        }

        return true;
    }
}