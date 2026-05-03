<?php
namespace App\Service;

use App\Entity\Avis;

class AvisManager
{
    public function validerContenu(Avis $a): bool
    {
        if (empty($a->getContenu())) {
            throw new \InvalidArgumentException('Le contenu de l\'avis est obligatoire.');
        }
        if (strlen((string)$a->getContenu()) < 10) {
            throw new \InvalidArgumentException('Le contenu doit contenir au moins 10 caractères.');
        }
        return true;
    }

    public function validerNbEtoiles(Avis $a): bool
    {
        if ($a->getNbEtoiles() === null) {
            throw new \InvalidArgumentException('La note est obligatoire.');
        }
        if ($a->getNbEtoiles() < 1) {
            throw new \InvalidArgumentException('La note minimum est 1 étoile.');
        }
        if ($a->getNbEtoiles() > 5) {
            throw new \InvalidArgumentException('La note maximum est 5 étoiles.');
        }
        return true;
    }

    public function validerStatut(Avis $a): bool
    {
        /** @var array<string> $valides */
        $valides = ['En attente', 'Validé', 'Rejeté'];
        if (!in_array($a->getStatut(), $valides, true)) {
            throw new \InvalidArgumentException('Le statut de l\'avis est invalide.');
        }
        return true;
    }

    public function validerSentiment(Avis $a): bool
    {
        if ($a->getSentimentLabel() === null) {
            return true;
        }
        /** @var array<string> $valides */
        $valides = ['positive', 'negative', 'neutral'];
        if (!in_array($a->getSentimentLabel(), $valides, true)) {
            throw new \InvalidArgumentException('Le sentiment doit être : positive, negative ou neutral.');
        }
        return true;
    }

    public function validerUserId(Avis $a): bool
    {
        if ($a->getUserId() === null || $a->getUserId() <= 0) {
            throw new \InvalidArgumentException('L\'identifiant utilisateur est invalide.');
        }
        return true;
    }

    public function validate(Avis $a): bool
    {
        $this->validerContenu($a);
        $this->validerNbEtoiles($a);
        $this->validerStatut($a);
        $this->validerSentiment($a);
        $this->validerUserId($a);
        return true;
    }
}
