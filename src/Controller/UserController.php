<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface      $entityManager,
        private UsersRepository             $usersRepository,
        private RoleRepository              $roleRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    // ── Liste des utilisateurs ──────────────────────────────────────────────────
    #[Route('/admin/users', name: 'admin_users_list')]
    public function listUsers(): Response
    {
        return $this->render('admin/users/list.html.twig', [
            'users' => $this->usersRepository->findAll(),
            'roles' => $this->roleRepository->findAll(),
        ]);
    }

    // ── Dashboard utilisateurs ──────────────────────────────────────────────────
    #[Route('/admin/users/dashboard', name: 'admin_users_dashboard')]
    public function dashboard(RoleRepository $roleRepository): Response
    {
        $users = $this->usersRepository->findAll();
        $roles = $roleRepository->findAll();

        $totalUsers    = count($users);
        $activeUsers   = count(array_filter($users, fn($u) => $u->isActive()));
        $inactiveUsers = $totalUsers - $activeUsers;
        $totalRoles    = count($roles);

        $latestUsers = $this->usersRepository->findBy([], ['createdAt' => 'DESC'], 5);

        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = new \DateTime("-$i months");
            $key  = $date->format('Y-m');
            $monthlyData[$key] = 0;
        }
        foreach ($users as $user) {
            if ($user->getCreatedAt()) {
                $key = $user->getCreatedAt()->format('Y-m');
                if (isset($monthlyData[$key])) {
                    $monthlyData[$key]++;
                }
            }
        }

        $roleStats = [];
        foreach ($users as $user) {
            foreach ($user->getRolesCollection() as $role) {
                $name = $role->getName();
                $roleStats[$name] = ($roleStats[$name] ?? 0) + 1;
            }
        }

        return $this->render('admin/dashboard.html.twig', [
            'totalUsers'    => $totalUsers,
            'activeUsers'   => $activeUsers,
            'inactiveUsers' => $inactiveUsers,
            'totalRoles'    => $totalRoles,
            'latestUsers'   => $latestUsers,
            'monthlyData'   => $monthlyData,
            'roleStats'     => $roleStats,
        ]);
    }

    // ── Ajouter un utilisateur ──────────────────────────────────────────────────
    #[Route('/admin/add-user', name: 'add_user')]
    public function addUser(Request $request): Response
    {
        $fieldErrors  = [];
        $globalError  = null;
        $successMsg   = null;
        $formData     = [];

        if ($request->isMethod('POST')) {
            $nom             = trim($request->request->get('nom', ''));
            $prenom          = trim($request->request->get('prenom', ''));
            $email           = trim($request->request->get('email', ''));
            $telephone       = trim($request->request->get('telephone', ''));
            $password        = $request->request->get('password', '');
            $confirmPassword = $request->request->get('confirm_password', '');
            $roleIds         = $request->request->all('roles');
            $isActive        = $request->request->get('is_active') === 'on';

            $formData = compact('nom', 'prenom', 'email', 'telephone');

            if (empty($nom)) {
                $fieldErrors['nom'] = 'Le nom est obligatoire.';
            } elseif (!preg_match('/^[A-Za-zÀ-ÿ\s]{2,50}$/', $nom)) {
                $fieldErrors['nom'] = 'Le nom doit contenir uniquement des lettres (2-50 caractères).';
            }

            if (empty($prenom)) {
                $fieldErrors['prenom'] = 'Le prénom est obligatoire.';
            } elseif (!preg_match('/^[A-Za-zÀ-ÿ\s]{2,50}$/', $prenom)) {
                $fieldErrors['prenom'] = 'Le prénom doit contenir uniquement des lettres (2-50 caractères).';
            }

            if (empty($email)) {
                $fieldErrors['email'] = "L'adresse email est obligatoire.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $fieldErrors['email'] = "Le format de l'adresse email est invalide.";
            } elseif ($this->usersRepository->findOneBy(['email' => $email])) {
                $fieldErrors['email'] = 'Cette adresse email est déjà utilisée par un autre compte.';
            }

            if (empty($telephone)) {
                $fieldErrors['telephone'] = 'Le numéro de téléphone est obligatoire.';
            } elseif (!preg_match('/^[0-9]{8}$/', $telephone)) {
                $fieldErrors['telephone'] = 'Le numéro de téléphone doit contenir exactement 8 chiffres.';
            }

            if (empty($password)) {
                $fieldErrors['password'] = 'Le mot de passe est obligatoire.';
            } elseif (strlen($password) < 8) {
                $fieldErrors['password'] = 'Le mot de passe doit contenir au moins 8 caractères.';
            } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])/', $password)) {
                $fieldErrors['password'] = 'Le mot de passe doit contenir une majuscule, une minuscule, un chiffre et un caractère spécial.';
            }

            if (empty($confirmPassword)) {
                $fieldErrors['confirm_password'] = 'La confirmation du mot de passe est obligatoire.';
            } elseif ($password !== $confirmPassword) {
                $fieldErrors['confirm_password'] = 'Les mots de passe ne correspondent pas.';
            }

            if (empty($roleIds)) {
                $fieldErrors['roles'] = 'Veuillez sélectionner au moins un rôle.';
            }

            if (!empty($fieldErrors)) {
                $globalError = 'Veuillez corriger les erreurs ci-dessous.';
            } else {
                try {
                    $user = new Users();
                    $user->setNom($nom);
                    $user->setPrenom($prenom);
                    $user->setEmail($email);
                    $user->setTelephone($telephone);
                    $user->setIsActive($isActive);
                    $user->setCreatedAt(new \DateTime());
                    $user->setUpdatedAt(new \DateTime());
                    $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));

                    foreach ($roleIds as $roleId) {
                        $role = $this->roleRepository->find((int) $roleId);
                        if ($role) {
                            $user->addRole($role);
                        }
                    }

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    $successMsg = 'L\'utilisateur ' . $nom . ' ' . $prenom . ' a été créé avec succès.';
                    $formData   = [];

                } catch (\Exception $e) {
                    $globalError = 'Une erreur est survenue : ' . $e->getMessage();
                }
            }
        }

        return $this->render('admin/add-user.html.twig', [
            'roles'       => $this->roleRepository->findAll(),
            'fieldErrors' => $fieldErrors,
            'globalError' => $globalError,
            'successMsg'  => $successMsg,
            'formData'    => $formData,
        ]);
    }

    // ── Modifier un utilisateur ─────────────────────────────────────────────────
    #[Route('/admin/users/{id}/edit', name: 'admin_user_edit')]
    public function editUser(Users $user, Request $request): Response
    {
        $fieldErrors = [];
        $globalError = null;

        if ($request->isMethod('POST')) {
            $nom       = trim($request->request->get('nom', ''));
            $prenom    = trim($request->request->get('prenom', ''));
            $email     = trim($request->request->get('email', ''));
            $telephone = trim($request->request->get('telephone', ''));
            $password  = $request->request->get('password', '');

            if (empty($nom)) {
                $fieldErrors['nom'] = 'Le nom est obligatoire.';
            }
            if (empty($prenom)) {
                $fieldErrors['prenom'] = 'Le prénom est obligatoire.';
            }
            if (empty($email)) {
                $fieldErrors['email'] = "L'adresse email est obligatoire.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $fieldErrors['email'] = "Le format de l'adresse email est invalide.";
            } else {
                $existingUser = $this->usersRepository->findOneBy(['email' => $email]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $fieldErrors['email'] = 'Cette adresse email est déjà utilisée par un autre compte.';
                }
            }

            if (empty($telephone)) {
                $fieldErrors['telephone'] = 'Le numéro de téléphone est obligatoire.';
            } elseif (!preg_match('/^[0-9]{8}$/', $telephone)) {
                $fieldErrors['telephone'] = 'Le numéro de téléphone doit contenir exactement 8 chiffres.';
            }

            if (!empty($password) && strlen($password) < 8) {
                $fieldErrors['password'] = 'Le mot de passe doit contenir au moins 8 caractères.';
            }

            if (!empty($fieldErrors)) {
                $globalError = 'Veuillez corriger les erreurs ci-dessous.';
            } else {
                $user->setNom($nom);
                $user->setPrenom($prenom);
                $user->setEmail($email);
                $user->setTelephone($telephone);
                $user->setIsActive($request->request->get('is_active') === 'on');
                $user->setUpdatedAt(new \DateTime());

                if (!empty($password)) {
                    $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
                }

                $user->getRolesCollection()->clear();
                foreach ($request->request->all('roles') as $roleId) {
                    $role = $this->roleRepository->find((int) $roleId);
                    if ($role) {
                        $user->addRole($role);
                    }
                }

                $this->entityManager->flush();
                $this->addFlash('success', 'Utilisateur mis à jour avec succès.');
                return $this->redirectToRoute('admin_users_list');
            }
        }

        return $this->render('admin/users/edit.html.twig', [
            'user'        => $user,
            'roles'       => $this->roleRepository->findAll(),
            'fieldErrors' => $fieldErrors,
            'globalError' => $globalError,
        ]);
    }

    // ── Supprimer un utilisateur ────────────────────────────────────────────────
    #[Route('/admin/users/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function deleteUser(Users $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_user_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_users_list');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof Users && $currentUser->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('admin_users_list');
        }

        $nom    = $user->getNom();
        $prenom = $user->getPrenom();

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', "L'utilisateur {$nom} {$prenom} a été supprimé avec succès.");
        return $this->redirectToRoute('admin_users_list');
    }

    // ── Activer / Désactiver ────────────────────────────────────────────────────
    #[Route('/admin/users/{id}/toggle', name: 'admin_user_toggle', methods: ['POST'])]
    public function toggleUser(Users $user, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('toggle_user_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_users_list');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof Users && $currentUser->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
            return $this->redirectToRoute('admin_users_list');
        }

        $user->setIsActive(!$user->isActive());
        $user->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        $statut = $user->isActive() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Le compte de {$user->getNom()} {$user->getPrenom()} a été {$statut} avec succès.");
        return $this->redirectToRoute('admin_users_list');
    }

    // ── Activités ───────────────────────────────────────────────────────────────
    #[Route('/admin/activities', name: 'admin_activities')]
    public function userActivities(): Response
    {
        $users = $this->usersRepository->findAll();
        return $this->render('admin/activities/index.html.twig', [
            'users' => $users
        ]);
    }

    // ── Profil d'un utilisateur ─────────────────────────────────────────────────
    #[Route('/admin/profile/{id}', name: 'admin_user_profile')]
    public function profile(Users $user): Response
    {
        return $this->render('admin/users/profile.html.twig', [
            'user' => $user,
        ]);
    }
}