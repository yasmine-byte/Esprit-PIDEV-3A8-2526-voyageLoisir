<?php

namespace App\Controller;

use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmailVerificationController extends AbstractController
{
    #[Route('/verify/email/{token}', name: 'verify_email')]
    public function verify(
        string $token,
        UsersRepository $usersRepository,
        EntityManagerInterface $em
    ): Response {
        $user = $usersRepository->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            $this->addFlash('register_error', 'Lien de vérification invalide ou expiré.');
            return $this->redirectToRoute('admin_login');
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setIsActive(true);
        $em->flush();

        $this->addFlash('register_success', '✔ Email vérifié avec succès ! Vous pouvez maintenant vous connecter.');
        return $this->redirectToRoute('admin_login');
    }
}