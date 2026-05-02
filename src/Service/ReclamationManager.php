<?php
namespace App\Service;

use App\Entity\Reclamation;

class ReclamationManager
{
    public function validerTitre(Reclamation $r): bool
    {
        if (empty($r->getTitre())) {
            throw new \InvalidArgumentException('Le titre est obligatoire.');
        }
        if (strlen((string)$r->getTitre()) < 5) {
            throw new \InvalidArgumentException('Le titre doit contenir au moins 5 caractères.');
        }
        if (strlen((string)$r->getTitre()) > 255) {
            throw new \InvalidArgumentException('Le titre ne peut pas dépasser 255 caractères.');
        }
        return true;
    }

    public function validerContenu(Reclamation $r): bool
    {
        if (empty($r->getContenu())) {
            throw new \InvalidArgumentException('Le contenu est obligatoire.');
        }
        if (strlen((string)$r->getContenu()) < 20) {
            throw new \InvalidArgumentException('Le contenu doit contenir au moins 20 caractères.');
        }
        return true;
    }

    public function validerPriorite(Reclamation $r): bool
    {
        /** @var array<string> $valides */
        $valides = ['Basse', 'Moyenne', 'Haute', 'Urgente'];
        if (!in_array($r->getPriorite(), $valides, true)) {
            throw new \InvalidArgumentException('La priorité doit être : Basse, Moyenne, Haute ou Urgente.');
        }
        return true;
    }

    public function validerStatut(Reclamation $r): bool
    {
        /** @var array<string> $valides */
        $valides = ['En attente', 'En cours', 'Résolue', 'Rejetée'];
        if (!in_array($r->getStatut(), $valides, true)) {
            throw new \InvalidArgumentException('Le statut est invalide.');
        }
        return true;
    }

    public function validerDateCreation(Reclamation $r): bool
    {
        if ($r->getDateCreation() === null) {
            throw new \InvalidArgumentException('La date de création est obligatoire.');
        }
        if ($r->getDateCreation() > new \DateTime()) {
            throw new \InvalidArgumentException('La date de création ne peut pas être dans le futur.');
        }
        return true;
    }

    public function validerUserId(Reclamation $r): bool
    {
        if ($r->getUserId() === null || $r->getUserId() <= 0) {
            throw new \InvalidArgumentException('L\'identifiant utilisateur est invalide.');
        }
        return true;
    }

    public function validate(Reclamation $r): bool
    {
        $this->validerTitre($r);
        $this->validerContenu($r);
        $this->validerPriorite($r);
        $this->validerStatut($r);
        $this->validerDateCreation($r);
        $this->validerUserId($r);
        return true;
    }
}
