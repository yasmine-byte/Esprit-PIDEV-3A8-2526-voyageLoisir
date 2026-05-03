<?php

namespace App\Service;

use App\Entity\Users;

class UserManager
{
    /**
     * Valide les règles métier d'un User.
     *
     * Règle 1 : Le nom est obligatoire
     * Règle 2 : Le prénom est obligatoire
     * Règle 3 : L'email est obligatoire et valide
     * Règle 4 : Le nom et prénom doivent avoir au moins 2 caractères
     * Règle 5 : Le téléphone doit contenir exactement 8 chiffres
     */
    public function validate(Users $user): bool
    {
        // Règle 1 : Le nom est obligatoire
        if (empty(trim((string) $user->getNom()))) {
            throw new \InvalidArgumentException('Le nom est obligatoire.');
        }

        // Règle 2 : Le prénom est obligatoire
        if (empty(trim((string) $user->getPrenom()))) {
            throw new \InvalidArgumentException('Le prénom est obligatoire.');
        }

        // Règle 3 : L'email est obligatoire et valide
        if (empty(trim((string) $user->getEmail()))) {
            throw new \InvalidArgumentException('L\'email est obligatoire.');
        }
        if (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('L\'email n\'est pas valide.');
        }

        // Règle 4 : Le nom et prénom doivent avoir au moins 2 caractères
        if (mb_strlen(trim((string) $user->getNom())) < 2) {
            throw new \InvalidArgumentException('Le nom doit contenir au moins 2 caractères.');
        }
        if (mb_strlen(trim((string) $user->getPrenom())) < 2) {
            throw new \InvalidArgumentException('Le prénom doit contenir au moins 2 caractères.');
        }

        // Règle 5 : Le téléphone doit contenir exactement 8 chiffres
        if ($user->getTelephone() !== null) {
            if (!preg_match('/^[0-9]{8}$/', $user->getTelephone())) {
                throw new \InvalidArgumentException('Le téléphone doit contenir exactement 8 chiffres.');
            }
        }

        return true;
    }
}