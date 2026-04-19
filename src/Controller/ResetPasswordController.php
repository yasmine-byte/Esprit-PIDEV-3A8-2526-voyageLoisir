<?php

namespace App\Controller;

use App\Repository\UsersRepository;
use App\Service\GmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ResetPasswordController extends AbstractController
{
    // ── Étape 1 : Formulaire "Mot de passe oublié" ─────────────────────────────
    #[Route('/forgot-password', name: 'forgot_password')]
    public function forgotPassword(
        Request $request,
        UsersRepository $usersRepository,
        EntityManagerInterface $em,
        GmailService $gmailService
    ): Response {
        $successMsg  = null;
        $errorMsg    = null;

        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorMsg = "Veuillez entrer une adresse email valide.";
            } else {
                $user = $usersRepository->findOneBy(['email' => $email]);

                if ($user) {
                    // Générer un token unique
                    $token   = bin2hex(random_bytes(32));
                    $expires = new \DateTime('+1 hour');

                    $user->setResetToken($token);
                    $user->setResetTokenExpiresAt($expires);
                    $em->flush();

                    // Générer le lien de reset
                    $resetLink = $this->generateUrl(
                        'reset_password',
                        ['token' => $token],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    );

                    // Envoyer l'email
                    try {
                        $gmailService->sendResetPasswordEmail(
                            $email,
                            $user->getPrenom() . ' ' . $user->getNom(),
                            $resetLink
                        );
                    } catch (\Exception $e) {}
                }

                // Toujours afficher le même message pour des raisons de sécurité
                $successMsg = "Si cette adresse email existe, vous recevrez un lien de réinitialisation.";
            }
        }

        return $this->render('admin/reset/forgot-password.html.twig', [
            'successMsg' => $successMsg,
            'errorMsg'   => $errorMsg,
        ]);
    }

    // ── Étape 2 : Formulaire de nouveau mot de passe ───────────────────────────
    #[Route('/reset-password/{token}', name: 'reset_password')]
    public function resetPassword(
        string $token,
        Request $request,
        UsersRepository $usersRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $usersRepository->findOneBy(['resetToken' => $token]);

        // Vérifier si le token est valide et non expiré
        if (!$user || $user->getResetTokenExpiresAt() < new \DateTime()) {
            $this->addFlash('register_error', 'Ce lien de réinitialisation est invalide ou expiré.');
            return $this->redirectToRoute('forgot_password');
        }

        $fieldErrors = [];
        $globalError = null;

        if ($request->isMethod('POST')) {
            $password        = $request->request->get('password', '');
            $confirmPassword = $request->request->get('confirm_password', '');

            // Validation mot de passe
            if (empty($password)) {
                $fieldErrors['password'] = 'Le mot de passe est obligatoire.';
            } elseif (strlen($password) < 6) {
                $fieldErrors['password'] = 'Le mot de passe doit contenir au moins 6 caractères.';
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $fieldErrors['password'] = 'Le mot de passe doit contenir au moins une majuscule.';
            } elseif (!preg_match('/[a-z]/', $password)) {
                $fieldErrors['password'] = 'Le mot de passe doit contenir au moins une minuscule.';
            } elseif (!preg_match('/[0-9]/', $password)) {
                $fieldErrors['password'] = 'Le mot de passe doit contenir au moins un chiffre.';
            } elseif (!preg_match('/[@$!%*?&#+\-_=.]/', $password)) {
                $fieldErrors['password'] = 'Le mot de passe doit contenir au moins un caractère spécial.';
            }

            if ($password !== $confirmPassword) {
                $fieldErrors['confirm_password'] = 'Les mots de passe ne correspondent pas.';
            }

            if (empty($fieldErrors)) {
                // Mettre à jour le mot de passe
                $user->setPasswordHash($passwordHasher->hashPassword($user, $password));
                $user->setResetToken(null);
                $user->setResetTokenExpiresAt(null);
                $user->setUpdatedAt(new \DateTime());
                $em->flush();

                $this->addFlash('success', 'Mot de passe réinitialisé avec succès !');
                return $this->redirectToRoute('admin_login');
            }

            $globalError = 'Veuillez corriger les erreurs ci-dessous.';
        }

        return $this->render('admin/reset/reset-password.html.twig', [
            'token'       => $token,
            'fieldErrors' => $fieldErrors,
            'globalError' => $globalError,
        ]);
    }
}
