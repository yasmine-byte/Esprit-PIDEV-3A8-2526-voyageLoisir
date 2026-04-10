<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use App\Repository\UsersRepository;

class SecurityController extends AbstractController
{
    #[Route('/admin/login', name: 'admin_login')]
    public function login(
        AuthenticationUtils $authenticationUtils,
        UsersRepository $usersRepository
    ): Response {
        // ── Si déjà connecté, rediriger selon le rôle ──
        $user = $this->getUser();
        if ($user) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_admin');
            }
            // ROLE_USER → profil uniquement
            return $this->redirectToRoute('admin_profile');
        }

        $error        = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        $fieldErrors  = [];
        $globalError  = null;

        if ($error !== null) {
            $emailSaisi = trim($lastUsername ?? '');

            // ── Cas 1 : compte désactivé (levé par UserChecker) ──
            if ($error instanceof CustomUserMessageAccountStatusException) {
                $fieldErrors['email'] = $error->getMessageKey();

            // ── Cas 2 : champ email vide ──
            } elseif (empty($emailSaisi)) {
                $fieldErrors['email'] = "L'adresse email est obligatoire.";

            // ── Cas 3 : format email invalide ──
            } elseif (!filter_var($emailSaisi, FILTER_VALIDATE_EMAIL)) {
                $fieldErrors['email'] = "Le format de l'adresse email est invalide.";

            } else {
                $existingUser = $usersRepository->findOneBy(['email' => $emailSaisi]);

                // ── Cas 4 : email introuvable en base ──
                if (!$existingUser) {
                    $fieldErrors['email'] = "Aucun compte trouvé avec cette adresse email.";
                } else {
                    // ── Cas 5 : mot de passe incorrect ──
                    $fieldErrors['password'] = "Mot de passe incorrect.";
                }
            }

            $globalError = "Veuillez corriger les erreurs ci-dessous.";
        }

        return $this->render('admin/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
            'fieldErrors'   => $fieldErrors,
            'globalError'   => $globalError,
        ]);
    }

    #[Route('/admin/login/check', name: 'admin_login_check')]
    public function check(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the firewall.');
    }
}
