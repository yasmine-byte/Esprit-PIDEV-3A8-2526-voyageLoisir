<?php

namespace App\Controller;

use App\Entity\Users;
use App\Entity\Role;
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
    private EntityManagerInterface $entityManager;
    private UsersRepository $usersRepository;
    private RoleRepository $roleRepository;
    private UserPasswordHasherInterface $passwordHasher; // ← AJOUTER

    public function __construct(
        EntityManagerInterface $entityManager,
        UsersRepository $usersRepository,
        RoleRepository $roleRepository,
        UserPasswordHasherInterface $passwordHasher // ← AJOUTER
    ) {
        $this->entityManager = $entityManager;
        $this->usersRepository = $usersRepository;
        $this->roleRepository = $roleRepository;
        $this->passwordHasher = $passwordHasher; // ← AJOUTER
    }

    #[Route('/admin/users', name: 'admin_users_list')]
    public function listUsers(): Response
    {
        $users = $this->usersRepository->findAll();
        $roles = $this->roleRepository->findAll();

        return $this->render('admin/users/list.html.twig', [
            'users' => $users,
            'roles' => $roles
        ]);
    }

    #[Route('/admin/users/{id}/edit', name: 'admin_user_edit')]
public function editUser(Users $user, Request $request, UserPasswordHasherInterface $passwordHasher): Response
{
    if ($request->isMethod('POST')) {
        $nom = $request->request->get('nom');
        $prenom = $request->request->get('prenom');
        $email = $request->request->get('email');
        $telephone = $request->request->get('telephone');
        $password = $request->request->get('password');

        // Validation des champs obligatoires
        if (empty($nom) || empty($prenom) || empty($email)) {
            $this->addFlash('error', 'Les champs nom, prénom et email sont obligatoires');
            return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
        }

        // Validation du téléphone (exactement 8 chiffres)
        if (!empty($telephone) && (!preg_match('/^[0-9]{8}$/', $telephone))) {
            $this->addFlash('error', 'Le téléphone doit contenir exactement 8 chiffres');
            return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
        }

        // Vérifier si l'email existe déjà (pour un autre utilisateur)
        $existingUser = $this->usersRepository->findOneBy(['email' => $email]);
        if ($existingUser && $existingUser->getId() !== $user->getId()) {
            $this->addFlash('error', 'Cet email est déjà utilisé par un autre utilisateur');
            return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
        }

        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setEmail($email);
        $user->setTelephone($telephone ?: null);
        $user->setIsActive($request->request->get('is_active') === 'on');
        $user->setUpdatedAt(new \DateTime());

        // Changer le mot de passe seulement si renseigné
        $password = $request->request->get('password');
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères');
                return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
            }
            $user->setPasswordHash($passwordHasher->hashPassword($user, $password));
        }

        // Mettre à jour les rôles
        $user->getRolesCollection()->clear();
        foreach ($request->request->all('roles') as $roleId) {
            $role = $this->roleRepository->find((int)$roleId);
            if ($role) $user->addRole($role);
        }

        $user->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        $this->addFlash('success', 'Utilisateur mis à jour avec succès');
        return $this->redirectToRoute('admin_users_list');
    }

    return $this->render('admin/users/edit.html.twig', [
        'user'  => $user,
        'roles' => $this->roleRepository->findAll()
    ]);
}

    #[Route('/admin/add-user', name: 'add_user')]
public function addUser(Request $request, UserPasswordHasherInterface $passwordHasher): Response
{
    if ($request->isMethod('POST')) {
        $nom             = $request->request->get('nom');
        $prenom          = $request->request->get('prenom');
        $email           = $request->request->get('email');
        $telephone       = $request->request->get('telephone');
        $password        = $request->request->get('password');
        $confirmPassword = $request->request->get('confirm_password');
        $roleIds         = $request->request->all('roles'); // ✅ CORRIGÉ
        $isActive        = $request->request->get('is_active') === 'on';

        if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
            $this->addFlash('error', 'Tous les champs obligatoires doivent être remplis');
            return $this->redirectToRoute('add_user');
        }

        if ($password !== $confirmPassword) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas');
            return $this->redirectToRoute('add_user');
        }

        if (strlen($password) < 8) {
            $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères');
            return $this->redirectToRoute('add_user');
        }

        // Validation du téléphone (exactement 8 chiffres)
        if (!empty($telephone) && (!preg_match('/^[0-9]{8}$/', $telephone))) {
            $this->addFlash('error', 'Le téléphone doit contenir exactement 8 chiffres');
            return $this->redirectToRoute('add_user');
        }

        $existingUser = $this->usersRepository->findOneBy(['email' => $email]);
        if ($existingUser) {
            $this->addFlash('error', 'Cet email est déjà utilisé');
            return $this->redirectToRoute('add_user');
        }

        try {
            $user = new Users();
            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setEmail($email);
            $user->setTelephone($telephone ?: null);
            $user->setIsActive($isActive);
            $user->setCreatedAt(new \DateTime());
            $user->setUpdatedAt(new \DateTime());

            // ✅ Hash correct compatible avec Symfony Security
            $hashedPassword = $passwordHasher->hashPassword($user, $password);
            $user->setPasswordHash($hashedPassword);

            foreach ($roleIds as $roleId) {
                $role = $this->roleRepository->find((int)$roleId);
                if ($role) {
                    $user->addRole($role);
                }
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès');
            return $this->redirectToRoute('admin_users_list');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            return $this->redirectToRoute('add_user');
        }
    }

    $roles = $this->roleRepository->findAll();
    return $this->render('admin/add-user.html.twig', [
        'roles' => $roles
    ]);
}

    #[Route('/admin/users/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
public function deleteUser(Users $user): Response
{
    $this->entityManager->remove($user);
    $this->entityManager->flush();

    $this->addFlash('success', 'Utilisateur supprimé avec succès');
    return $this->redirectToRoute('admin_users_list');
}

    #[Route('/admin/activities', name: 'admin_activities')]
    public function userActivities(): Response
    {
        $users = $this->usersRepository->findAll();

        return $this->render('admin/activities/index.html.twig', [
            'users' => $users
        ]);
    }
    #[Route('/admin/profile/{id}', name: 'admin_user_profile')]
public function profile(Users $user): Response
{
    return $this->render('admin/users/profile.html.twig', [
        'user' => $user,
    ]);
}
}
