<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Repository\VoyageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Vich\UploaderBundle\Handler\UploadHandler;

class FrontProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UsersRepository        $usersRepository,
    ) {}

    private function validatePassword(string $password): ?string
    {
        if (strlen($password) < 6) return 'Le mot de passe doit contenir au moins 6 caractères.';
        if (!preg_match('/[A-Z]/', $password)) return 'Le mot de passe doit contenir au moins une majuscule.';
        if (!preg_match('/[a-z]/', $password)) return 'Le mot de passe doit contenir au moins une minuscule.';
        if (!preg_match('/[0-9]/', $password)) return 'Le mot de passe doit contenir au moins un chiffre.';
        if (!preg_match('/[@$!%*?&#+\-_=.]/', $password)) return 'Le mot de passe doit contenir au moins un caractère spécial.';
        return null;
    }

    #[Route('/profile', name: 'front_profile')]
    public function profile(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('admin_login');
        }

        if (!$user instanceof Users) {
            $user = $this->usersRepository->findOneBy(['email' => $user->getUserIdentifier()]);
        }

        return $this->render('home/profile.html.twig', [
            'user'        => $user,
            'fieldErrors' => [],
            'globalError' => null,
        ]);
    }

    #[Route('/profile/edit', name: 'front_profile_edit', methods: ['POST'])]
    public function editProfile(
        Request $request,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('admin_login');
        }

        if (!$user instanceof Users) {
            $user = $this->usersRepository->findOneBy(['email' => $user->getUserIdentifier()]);
        }

        $tab         = $request->request->get('tab', 'edit');
        $fieldErrors = [];
        $globalError = null;

        if ($tab === 'edit') {
            // ── Modification des informations ──
            $nom             = trim($request->request->get('nom', ''));
            $prenom          = trim($request->request->get('prenom', ''));
            $email           = trim($request->request->get('email', ''));
            $telephone       = trim($request->request->get('telephone', ''));
            $currentPassword = $request->request->get('current_password', '');

            if (empty($nom) || !preg_match('/^[A-Za-zÀ-ÿ\s]{2,50}$/', $nom))
                $fieldErrors['nom'] = empty($nom) ? 'Le nom est obligatoire.' : 'Le nom doit contenir uniquement des lettres.';

            if (empty($prenom) || !preg_match('/^[A-Za-zÀ-ÿ\s]{2,50}$/', $prenom))
                $fieldErrors['prenom'] = empty($prenom) ? 'Le prénom est obligatoire.' : 'Le prénom doit contenir uniquement des lettres.';

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $fieldErrors['email'] = empty($email) ? "L'email est obligatoire." : "Format d'email invalide.";
            } else {
                $existing = $this->usersRepository->findOneBy(['email' => $email]);
                if ($existing && $existing->getId() !== $user->getId())
                    $fieldErrors['email'] = 'Cet email est déjà utilisé.';
            }

            if (!empty($telephone) && !preg_match('/^[0-9]{8}$/', $telephone))
                $fieldErrors['telephone'] = 'Le téléphone doit contenir exactement 8 chiffres.';

            $hasChanges = ($nom !== $user->getNom() || $prenom !== $user->getPrenom()
                || $email !== $user->getEmail() || $telephone !== $user->getTelephone());

            if ($hasChanges && empty($currentPassword)) {
                $fieldErrors['current_password'] = 'Veuillez entrer votre mot de passe actuel.';
            } elseif ($hasChanges && !$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $fieldErrors['current_password'] = 'Mot de passe actuel incorrect.';
            }

            if (empty($fieldErrors)) {
                $user->setNom($nom);
                $user->setPrenom($prenom);
                $user->setEmail($email);
                $user->setTelephone($telephone ?: null);
                $user->setUpdatedAt(new \DateTime());

                // Upload avatar
                $avatarFile = $request->files->get('avatarFile');
                if ($avatarFile) {
                    $user->setAvatarFile($avatarFile);
                }

                $this->entityManager->flush();
                $this->addFlash('success', 'Profil mis à jour avec succès.');
                return $this->redirectToRoute('front_profile');
            }

        } elseif ($tab === 'password') {
            // ── Changement de mot de passe ──
            $currentPassword = $request->request->get('current_password', '');
            $newPassword     = $request->request->get('new_password', '');
            $confirmPassword = $request->request->get('confirm_password', '');

            if (empty($currentPassword)) {
                $fieldErrors['current_password'] = 'Le mot de passe actuel est obligatoire.';
            } elseif (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $fieldErrors['current_password'] = 'Mot de passe actuel incorrect.';
            }

            $passwordError = $this->validatePassword($newPassword);
            if ($passwordError) $fieldErrors['new_password'] = $passwordError;

            if ($newPassword !== $confirmPassword)
                $fieldErrors['confirm_password'] = 'Les mots de passe ne correspondent pas.';

            if (empty($fieldErrors)) {
                $user->setPasswordHash($passwordHasher->hashPassword($user, $newPassword));
                $user->setUpdatedAt(new \DateTime());
                $this->entityManager->flush();
                $this->addFlash('success', 'Mot de passe changé avec succès.');
                return $this->redirectToRoute('front_profile');
            }
        }

        if (!empty($fieldErrors)) $globalError = 'Veuillez corriger les erreurs ci-dessous.';

        return $this->render('home/profile.html.twig', [
            'user'        => $user,
            'fieldErrors' => $fieldErrors,
            'globalError' => $globalError,
        ]);
        // Vérifier si compte Google
$isGoogleAccount = $user->getPasswordHash() === 'GOOGLE_OAUTH_NO_PASSWORD';
if ($hasChanges && !$isGoogleAccount && empty($currentPassword)) {
    $fieldErrors['current_password'] = 'Veuillez entrer votre mot de passe actuel.';
} elseif ($hasChanges && !$isGoogleAccount && !$passwordHasher->isPasswordValid($user, $currentPassword)) {
    $fieldErrors['current_password'] = 'Mot de passe actuel incorrect.';
}
    }
}