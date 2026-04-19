<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class FrontLoginController extends AbstractController
{
    #[Route('/login', name: 'front_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si l'utilisateur est déjà connecté, rediriger vers le profil
        if ($this->getUser()) {
            return $this->redirectToRoute('front_profile');
        }

        // Récupérer les erreurs de connexion
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('front/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }
}