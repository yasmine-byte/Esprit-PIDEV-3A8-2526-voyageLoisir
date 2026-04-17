<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\Users;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UsersRepository        $usersRepository
    ) {}

    #[Route('/admin/profile', name: 'admin_profile')]
    public function showProfile(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('admin_login');
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin');
        }

        if (!$user instanceof Users) {
            $user = $this->usersRepository->findOneBy(['email' => $user->getUserIdentifier()]);
        }

        return $this->render('admin/users/profile.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/admin/profile/edit', name: 'admin_profile_edit')]
    public function editProfile(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('admin_login');
        }

        if (!$user instanceof Users) {
            $user = $this->usersRepository->findOneBy(['email' => $user->getUserIdentifier()]);
        }

        if (!$user) {
            return $this->redirectToRoute('admin_login');
        }

        $fieldErrors = [];
        $globalError = null;

        if ($request->isMethod('POST')) {
            $nom             = trim($request->request->get('nom', ''));
            $prenom          = trim($request->request->get('prenom', ''));
            $email           = trim($request->request->get('email', ''));
            $telephone       = trim($request->request->get('telephone', ''));
            $currentPassword = $request->request->get('current_password', '');
            $newPassword     = $request->request->get('new_password', '');
            $confirmPassword = $request->request->get('confirm_password', '');

            if (empty($nom))    { $fieldErrors['nom']    = 'Le nom est obligatoire.'; }
            if (empty($prenom)) { $fieldErrors['prenom'] = 'Le prénom est obligatoire.'; }
            if (empty($email))  { $fieldErrors['email']  = "L'email est obligatoire."; }
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $fieldErrors['email'] = "Format d'email invalide.";
            } else {
                $existingUser = $this->usersRepository->findOneBy(['email' => $email]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $fieldErrors['email'] = 'Cet email est déjà utilisé.';
                }
            }

            $hasChanges = ($nom !== $user->getNom() || $prenom !== $user->getPrenom()
                || $email !== $user->getEmail() || $telephone !== $user->getTelephone());

            if ($hasChanges && empty($currentPassword)) {
                $fieldErrors['current_password'] = 'Veuillez entrer votre mot de passe actuel.';
            } elseif ($hasChanges && !empty($currentPassword) && !$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $fieldErrors['current_password'] = 'Mot de passe actuel incorrect.';
            }

            if (!empty($newPassword)) {
                if (empty($currentPassword)) {
                    $fieldErrors['current_password'] = 'Veuillez entrer votre mot de passe actuel.';
                } elseif (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $fieldErrors['current_password'] = 'Mot de passe actuel incorrect.';
                }
                if (strlen($newPassword) < 8) {
                    $fieldErrors['new_password'] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
                }
                if ($newPassword !== $confirmPassword) {
                    $fieldErrors['confirm_password'] = 'Les mots de passe ne correspondent pas.';
                }
            }

            if (!empty($fieldErrors)) {
                $globalError = 'Veuillez corriger les erreurs ci-dessous.';
            } else {
                try {
                    $user->setNom($nom);
                    $user->setPrenom($prenom);
                    $user->setEmail($email);
                    $user->setTelephone($telephone ?: null);

                    if (!empty($newPassword)) {
                        $user->setPasswordHash($passwordHasher->hashPassword($user, $newPassword));
                    }

                    $user->setUpdatedAt(new \DateTime());
                    $this->entityManager->flush();

                    $this->addFlash('success', 'Profil mis à jour avec succès');
                    return $this->redirectToRoute('admin_profile');

                } catch (\Exception $e) {
                    $globalError = 'Une erreur est survenue : ' . $e->getMessage();
                }
            }
        }

        return $this->render('admin/users/profile.html.twig', [
            'user'        => $user,
            'fieldErrors' => $fieldErrors,
            'globalError' => $globalError,
        ]);
    }
}