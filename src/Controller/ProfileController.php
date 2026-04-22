<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Repository\VoyageRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Vich\UploaderBundle\Handler\UploadHandler;


class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface   $entityManager,
        private UsersRepository          $usersRepository,
        private VoyageRepository         $voyageRepository,
        private ReservationRepository    $reservationRepository,
        private UploadHandler            $uploadHandler
    ) {}

    private function validatePassword(string $password): ?string
    {
        if (strlen($password) < 6) {
            return 'Le mot de passe doit contenir au moins 6 caractères.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Le mot de passe doit contenir au moins une lettre majuscule.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Le mot de passe doit contenir au moins une lettre minuscule.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Le mot de passe doit contenir au moins un chiffre.';
        }
        if (!preg_match('/[@$!%*?&#+\-_=.]/', $password)) {
            return 'Le mot de passe doit contenir au moins un caractère spécial (@$!%*?&#+).';
        }
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

        // Vérifier si un admin consulte le profil d'un autre utilisateur
        $session = $request->getSession();
        $viewingUserId = $session->get('viewing_user_id');

        if ($viewingUserId && $this->isGranted('ROLE_ADMIN')) {
            $viewedUser = $this->usersRepository->find($viewingUserId);
            if ($viewedUser) {
                $user = $viewedUser;
            }
            $session->remove('viewing_user_id');
        }

        // Récupérer les voyages réservés par l'utilisateur (relation ManyToMany)
        $conn = $this->entityManager->getConnection();
$rows = $conn->fetchAllAssociative(
    'SELECT v.id, vr.paid FROM voyage v 
     INNER JOIN voyage_reservations vr ON vr.voyage_id = v.id 
     WHERE vr.users_id = :uid',
    ['uid' => $user->getId()]
);
$paidMap = [];
foreach ($rows as $row) {
    $paidMap[$row['id']] = (bool)$row['paid'];
}

$conn = $this->entityManager->getConnection();
$paidRows = $conn->fetchAllAssociative(
    'SELECT voyage_id FROM voyage_reservations WHERE users_id = :uid AND paid = 1',
    ['uid' => $user->getId()]
);
$paidIds = array_column($paidRows, 'voyage_id');

$reservations = $this->voyageRepository->createQueryBuilder('v')
    ->innerJoin('v.reservedByUsers', 'u')
    ->where('u = :user')
    ->setParameter('user', $user)
    ->getQuery()
    ->getResult();

foreach ($reservations as $voyage) {
    $voyage->setPaid(in_array($voyage->getId(), $paidIds));
}

foreach ($reservations as $voyage) {
    $voyage->setPaid($paidMap[$voyage->getId()] ?? false);
}

        // Récupérer les réservations hébergement via l'email du client
        $reservationsHebergement = $this->reservationRepository->findBy(['clientEmail' => $user->getEmail()]);

        return $this->render('home/profile.html.twig', [
            'user'                    => $user,
            'reservations'            => $reservations,
            'reservationsHebergement' => $reservationsHebergement,
            'fieldErrors'             => [],
            'globalError'             => null,
        ]);
    }

    #[Route('/profile/edit', name: 'front_profile_edit', methods: ['POST'])]
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

            // ── Validation Nom ──
            if (empty($nom)) {
                $fieldErrors['nom'] = 'Le nom est obligatoire.';
            } elseif (!preg_match('/^[A-Za-zÀ-ÿ\s]{2,50}$/', $nom)) {
                $fieldErrors['nom'] = 'Le nom doit contenir uniquement des lettres (2-50 caractères).';
            }

            // ── Validation Prénom ──
            if (empty($prenom)) {
                $fieldErrors['prenom'] = 'Le prénom est obligatoire.';
            } elseif (!preg_match('/^[A-Za-zÀ-ÿ\s]{2,50}$/', $prenom)) {
                $fieldErrors['prenom'] = 'Le prénom doit contenir uniquement des lettres (2-50 caractères).';
            }

            // ── Validation Email ──
            if (empty($email)) {
                $fieldErrors['email'] = "L'adresse email est obligatoire.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $fieldErrors['email'] = "Le format de l'adresse email est invalide.";
            } else {
                $existingUser = $this->usersRepository->findOneBy(['email' => $email]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $fieldErrors['email'] = 'Cet email est déjà utilisé par un autre utilisateur.';
                }
            }

            // ── Validation Téléphone ──
            if (empty($telephone)) {
                $fieldErrors['telephone'] = 'Le numéro de téléphone est obligatoire.';
            } elseif (!preg_match('/^[0-9]{8}$/', $telephone)) {
                $fieldErrors['telephone'] = 'Le numéro de téléphone doit contenir exactement 8 chiffres.';
            }

            // ── Vérification mot de passe actuel ──
            $hasChanges = ($nom !== $user->getNom()
                || $prenom !== $user->getPrenom()
                || $email !== $user->getEmail()
                || $telephone !== $user->getTelephone());

            if ($hasChanges && empty($currentPassword)) {
                $fieldErrors['current_password'] = 'Veuillez entrer votre mot de passe actuel pour modifier vos informations.';
            } elseif ($hasChanges && !empty($currentPassword) && !$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $fieldErrors['current_password'] = 'Mot de passe actuel incorrect.';
            }

            // ── Validation nouveau mot de passe ──
            if (!empty($newPassword)) {
                if (empty($currentPassword)) {
                    $fieldErrors['current_password'] = 'Veuillez entrer votre mot de passe actuel.';
                } elseif (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $fieldErrors['current_password'] = 'Mot de passe actuel incorrect.';
                }

                $passwordError = $this->validatePassword($newPassword);
                if ($passwordError) {
                    $fieldErrors['new_password'] = $passwordError;
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

                    $avatarFile = $request->files->get('avatarFile');
                    if ($avatarFile) {
                        $user->setAvatarFile($avatarFile);
                    }

                    $user->setUpdatedAt(new \DateTime());
                    $this->entityManager->flush();

                    $this->addFlash('success', 'Profil mis à jour avec succès.');
                    return $this->redirectToRoute('front_profile');

                } catch (\Exception $e) {
                    $globalError = 'Une erreur est survenue : ' . $e->getMessage();
                }
            }
        }

        // Récupérer les réservations pour le re-rendu en cas d'erreur
        $conn = $this->entityManager->getConnection();
$paidRows = $conn->fetchAllAssociative(
    'SELECT voyage_id FROM voyage_reservations WHERE users_id = :uid AND paid = 1',
    ['uid' => $user->getId()]
);
$paidIds = array_column($paidRows, 'voyage_id');

$reservations = $this->voyageRepository->createQueryBuilder('v')
    ->innerJoin('v.reservedByUsers', 'u')
    ->where('u = :user')
    ->setParameter('user', $user)
    ->getQuery()
    ->getResult();

foreach ($reservations as $voyage) {
    $voyage->setPaid(in_array($voyage->getId(), $paidIds));
}
        $reservationsHebergement = $this->reservationRepository->findBy(['clientEmail' => $user->getEmail()]);

        return $this->render('home/profile.html.twig', [
            'user'                    => $user,
            'reservations'            => $reservations,
            'reservationsHebergement' => $reservationsHebergement,
            'fieldErrors'             => $fieldErrors,
            'globalError'             => $globalError,
        ]);
    }
}