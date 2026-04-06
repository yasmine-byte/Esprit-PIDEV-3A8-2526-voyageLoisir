<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;

class ProfileController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/admin/profile', name: 'admin_profile')]
    public function showProfile(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('admin_login');
        }

        // Si l'utilisateur est de type UserInterface, on récupère l'entité complète
        if (!$user instanceof Users) {
            $user = $this->entityManager->getRepository(Users::class)->findOneBy(['email' => $user->getUserIdentifier()]);
        }

        return $this->render('admin/users/profile.html.twig', [
            'user' => $user
        ]);
    }

    #[Route('/admin/profile/edit', name: 'admin_profile_edit')]
    public function editProfile(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('admin_login');
        }

        // Si l'utilisateur est de type UserInterface, on récupère l'entité complète
        if (!$user instanceof Users) {
            $user = $this->entityManager->getRepository(Users::class)->findOneBy(['email' => $user->getUserIdentifier()]);
        }

        if ($request->isMethod('POST')) {
            $nom = $request->request->get('nom');
            $prenom = $request->request->get('prenom');
            $email = $request->request->get('email');
            $telephone = $request->request->get('telephone');
            $currentPassword = $request->request->get('current_password');
            $newPassword = $request->request->get('new_password');
            $confirmPassword = $request->request->get('confirm_password');

            // Validation
            if (empty($nom) || empty($prenom) || empty($email)) {
                $this->addFlash('error', 'Les champs nom, prénom et email sont obligatoires');
                return $this->redirectToRoute('admin_profile_edit');
            }

            // Vérifier le mot de passe actuel si modification du profil
            if ($nom !== $user->getNom() || $prenom !== $user->getPrenom() || $email !== $user->getEmail() || $telephone !== $user->getTelephone()) {
                if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $this->addFlash('error', 'Mot de passe actuel incorrect pour modifier vos informations');
                    return $this->redirectToRoute('admin_profile_edit');
                }
            }

            // Vérifier si l'email existe déjà (pour un autre utilisateur)
            $existingUser = $this->entityManager->getRepository(Users::class)->findOneBy(['email' => $email]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                $this->addFlash('error', 'Cet email est déjà utilisé par un autre utilisateur');
                return $this->redirectToRoute('admin_profile_edit');
            }

            try {
                // Mettre à jour les informations
                $user->setNom($nom);
                $user->setPrenom($prenom);
                $user->setEmail($email);
                $user->setTelephone($telephone ?: null);

                // Changer le mot de passe si fourni
                if (!empty($newPassword)) {
                    if (empty($currentPassword)) {
                        $this->addFlash('error', 'Veuillez entrer votre mot de passe actuel');
                        return $this->redirectToRoute('admin_profile_edit');
                    }

                    if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                        $this->addFlash('error', 'Mot de passe actuel incorrect');
                        return $this->redirectToRoute('admin_profile_edit');
                    }

                    if (strlen($newPassword) < 8) {
                        $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères');
                        return $this->redirectToRoute('admin_profile_edit');
                    }

                    if ($newPassword !== $confirmPassword) {
                        $this->addFlash('error', 'Les mots de passe ne correspondent pas');
                        return $this->redirectToRoute('admin_profile_edit');
                    }

                    $user->setPasswordHash($passwordHasher->hashPassword($user, $newPassword));
                }

                $user->setUpdatedAt(new \DateTime());

                $this->entityManager->flush();

                $this->addFlash('success', 'Profil mis à jour avec succès');
                return $this->redirectToRoute('admin_profile');

            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue: ' . $e->getMessage());
                return $this->redirectToRoute('admin_profile_edit');
            }
        }

        return $this->render('admin/users/profile.html.twig', [
            'user' => $user
        ]);
    }
}
