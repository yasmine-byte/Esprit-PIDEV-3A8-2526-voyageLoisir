<?php
namespace App\Service;

use App\Entity\Users;

class UsersManager
{
    public function validate(Users $user): bool
    {
        if (empty($user->getNom())) {
            throw new \InvalidArgumentException('Le nom ne peut pas être vide');
        }
        if (strlen($user->getNom()) < 2) {
            throw new \InvalidArgumentException('Le nom doit contenir au moins 2 caractères');
        }
        if (empty($user->getEmail())) {
            throw new \InvalidArgumentException('L\'email ne peut pas être vide');
        }
        if (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Le format de l\'email est invalide');
        }
        if ($user->getTelephone() && !preg_match('/^\+216[0-9]{8}$/', $user->getTelephone())) {
            throw new \InvalidArgumentException('Le numéro de téléphone doit être au format +216XXXXXXXX');
        }
        return true;
    }

    public function isEligibleForReservation(Users $user): bool
    {
        if (!$user->isActive()) {
            throw new \InvalidArgumentException('L\'utilisateur doit être actif pour faire une réservation');
        }
        if (!$user->isVerified()) {
            throw new \InvalidArgumentException('L\'utilisateur doit être vérifié pour faire une réservation');
        }
        return true;
    }

    public function validateEmail(string $email): bool
    {
        if (empty($email)) {
            throw new \InvalidArgumentException('L\'email ne peut pas être vide');
        }
        if (strlen($email) > 150) {
            throw new \InvalidArgumentException('L\'email ne peut pas dépasser 150 caractères');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Le format de l\'email est invalide');
        }
        return true;
    }

    public function validateTelephone(string $telephone): bool
    {
        if (empty($telephone)) {
            throw new \InvalidArgumentException('Le numéro de téléphone ne peut pas être vide');
        }
        if (!preg_match('/^\+216[0-9]{8}$/', $telephone)) {
            throw new \InvalidArgumentException('Le numéro de téléphone doit être au format +216XXXXXXXX');
        }
        return true;
    }
}