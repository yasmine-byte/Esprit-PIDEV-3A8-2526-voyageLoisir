<?php

namespace App\Service;

use App\Entity\Reclamation;

class ReclamationManager
{
    public function validate(Reclamation $reclamation): bool
    {
        if (empty(trim($reclamation->getTitre()))) {
            throw new \InvalidArgumentException('Le titre est obligatoire.');
        }

        if (empty(trim($reclamation->getContenu()))) {
            throw new \InvalidArgumentException('Le contenu est obligatoire.');
        }

        if (mb_strlen(trim($reclamation->getTitre())) < 5) {
            throw new \InvalidArgumentException('Le titre doit contenir au moins 5 caractères.');
        }

        if (mb_strlen(trim($reclamation->getContenu())) < 10) {
            throw new \InvalidArgumentException('Le contenu doit contenir au moins 10 caractères.');
        }

        $prioritesValides = ['Basse', 'Moyenne', 'Haute', 'Urgente'];
        if (!in_array($reclamation->getPriorite(), $prioritesValides, true)) {
            throw new \InvalidArgumentException('La priorité doit être : Basse, Moyenne, Haute ou Urgente.');
        }

        $statutsValides = ['en_attente', 'en_cours', 'resolue', 'rejetee'];
        if (!in_array($reclamation->getStatut(), $statutsValides, true)) {
            throw new \InvalidArgumentException('Le statut doit être : en_attente, en_cours, resolue ou rejetee.');
        }

        return true;
    }
}