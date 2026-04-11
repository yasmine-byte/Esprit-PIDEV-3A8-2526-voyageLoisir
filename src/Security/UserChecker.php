<?php

namespace App\Security;

use App\Entity\Users;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    /**
     * Appelé AVANT la vérification du mot de passe.
     * Si le compte est inactif, on bloque immédiatement.
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Users) {
            return;
        }

        if ($user->isActive() === false || $user->isActive() === null) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte est désactivé. Veuillez contacter l\'administrateur.'
            );
        }
    }

    /**
     * Appelé APRÈS la vérification du mot de passe.
     */
    public function checkPostAuth(UserInterface $user): void
    {
        // Rien à faire ici
    }
}
