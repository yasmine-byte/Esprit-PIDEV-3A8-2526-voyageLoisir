<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use App\Repository\UsersRepository;
use Symfony\Component\HttpFoundation\Request;

class SecurityController extends AbstractController
{
    #[Route('/admin/login', name: 'admin_login')]
public function login(
    Request $request,
    AuthenticationUtils $authenticationUtils,
    UsersRepository $usersRepository
): Response {
    if ($this->getUser()) {
        return $this->redirectToRoute('app_home');
    }

    $error        = $authenticationUtils->getLastAuthenticationError();
    $lastUsername = $authenticationUtils->getLastUsername();
    $fieldErrors  = [];
    $globalError  = null;

    if ($error !== null) {
        $emailSaisi = trim($lastUsername ?? '');

        if ($error instanceof CustomUserMessageAccountStatusException) {
            $fieldErrors['email'] = $error->getMessageKey();
        } elseif (empty($emailSaisi)) {
            $fieldErrors['email'] = "L'adresse email est obligatoire.";
        } elseif (!filter_var($emailSaisi, FILTER_VALIDATE_EMAIL)) {
            $fieldErrors['email'] = "Le format de l'adresse email est invalide.";
        } else {
            $existingUser = $usersRepository->findOneBy(['email' => $emailSaisi]);
            if (!$existingUser) {
                $fieldErrors['email'] = "Aucun compte trouvé avec cette adresse email.";
            } else {
                $fieldErrors['password'] = "Mot de passe incorrect.";
            }
        }

        $globalError = "Veuillez corriger les erreurs ci-dessous.";
    }

    // ── Récupérer les erreurs d'inscription depuis la session ──
    $registerFieldErrors = $request->getSession()->get('register_field_errors', []);
    $registerFormData    = $request->getSession()->get('register_form_data', []);
    $request->getSession()->remove('register_field_errors');
    $request->getSession()->remove('register_form_data');

    return $this->render('admin/login.html.twig', [
        'last_username'       => $lastUsername,
        'error'               => $error,
        'fieldErrors'         => $fieldErrors,
        'globalError'         => $globalError,
        'registerFieldErrors' => $registerFieldErrors,
        'registerFormData'    => $registerFormData,
    ]);
}
    #[Route('/auth/check', name: 'admin_login_check')]
    public function check(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the firewall.');
    }
}
