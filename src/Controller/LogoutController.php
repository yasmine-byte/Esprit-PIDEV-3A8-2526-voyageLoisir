<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LogoutController extends AbstractController
{
    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): Response
    {
        // Cette méthode ne sera jamais exécutée car Symfony gère la déconnexion automatiquement
        // Le firewall intercepte cette route et effectue la déconnexion
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
