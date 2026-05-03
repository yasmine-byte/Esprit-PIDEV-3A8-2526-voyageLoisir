<?php

namespace App\Service;

use App\Entity\Destination;

class DestinationManager
{
    public function validate(Destination $destination): bool
    {
        // nom obligatoire
        if (empty($destination->getNom())) {
            throw new \InvalidArgumentException('Le nom est obligatoire.');
        }
        // nom : min 2, max 100 caractères
        if (strlen($destination->getNom()) < 2) {
            throw new \InvalidArgumentException('Le nom doit contenir au moins 2 caractères.');
        }
        if (strlen($destination->getNom()) > 100) {
            throw new \InvalidArgumentException('Le nom ne peut pas dépasser 100 caractères.');
        }
        // nom : pas de chiffres
        if (preg_match('/[0-9]/', $destination->getNom())) {
            throw new \InvalidArgumentException('Le nom ne doit pas contenir de chiffres.');
        }

        // pays obligatoire
        if (empty($destination->getPays())) {
            throw new \InvalidArgumentException('Le pays est obligatoire.');
        }
        if (strlen($destination->getPays()) < 2) {
            throw new \InvalidArgumentException('Le pays doit contenir au moins 2 caractères.');
        }
        if (strlen($destination->getPays()) > 100) {
            throw new \InvalidArgumentException('Le pays ne peut pas dépasser 100 caractères.');
        }
        if (preg_match('/[0-9]/', $destination->getPays())) {
            throw new \InvalidArgumentException('Le pays ne doit pas contenir de chiffres.');
        }

        // description obligatoire
        if (empty($destination->getDescription())) {
            throw new \InvalidArgumentException('La description est obligatoire.');
        }
        if (strlen($destination->getDescription()) < 20) {
            throw new \InvalidArgumentException('La description doit contenir au moins 20 caractères.');
        }
        if (strlen($destination->getDescription()) > 2000) {
            throw new \InvalidArgumentException('La description ne peut pas dépasser 2000 caractères.');
        }

        // statut obligatoire
        if ($destination->isStatut() === null) {
            throw new \InvalidArgumentException('Le statut est obligatoire.');
        }

        // meilleure saison
        if (empty($destination->getMeilleureSaison())) {
            throw new \InvalidArgumentException('La saison est obligatoire.');
        }
        $saisons = ['Printemps', 'Ete', 'Automne', 'Hiver'];
        if (!in_array($destination->getMeilleureSaison(), $saisons)) {
            throw new \InvalidArgumentException('Saison invalide.');
        }

        // latitude
        if ($destination->getLatitude() === null) {
            throw new \InvalidArgumentException('La latitude est obligatoire.');
        }
        if ($destination->getLatitude() < -90 || $destination->getLatitude() > 90) {
            throw new \InvalidArgumentException('La latitude doit être entre -90 et 90.');
        }

        // longitude
        if ($destination->getLongitude() === null) {
            throw new \InvalidArgumentException('La longitude est obligatoire.');
        }
        if ($destination->getLongitude() < -180 || $destination->getLongitude() > 180) {
            throw new \InvalidArgumentException('La longitude doit être entre -180 et 180.');
        }

        // nb_visites
        if ($destination->getNbVisites() === null) {
            throw new \InvalidArgumentException('Le nombre de visites est obligatoire.');
        }
        if ($destination->getNbVisites() < 0) {
            throw new \InvalidArgumentException('Le nombre de visites doit être zéro ou positif.');
        }

        return true;
    }
}