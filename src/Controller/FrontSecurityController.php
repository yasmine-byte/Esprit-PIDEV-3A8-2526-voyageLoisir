<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class FrontSecurityController extends AbstractController
{
    #[Route('/login', name: 'front_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }
        return $this->redirectToRoute('app_home');
    }

    #[Route('/login/check', name: 'front_login_check')]
    public function check(): void
    {
        throw new \LogicException('Intercepted by firewall.');
    }

    #[Route('/logout-front', name: 'front_logout')]
    public function logout(): void
    {
        throw new \LogicException('Intercepted by firewall.');
    }

    #[Route('/register', name: 'front_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        UsersRepository $usersRepository,
        RoleRepository $roleRepository
    ): Response {
        $nom             = trim($request->request->get('nom', ''));
        $prenom          = trim($request->request->get('prenom', ''));
        $email           = trim($request->request->get('email', ''));
        $telephone       = trim($request->request->get('telephone', ''));
        $password        = $request->request->get('password', '');
        $confirmPassword = $request->request->get('confirm_password', '');

        // ── Validations ──
        if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
            $this->addFlash('register_error', 'Tous les champs obligatoires doivent être remplis.');
            return $this->redirectToRoute('admin_login');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('register_error', "Le format de l'adresse email est invalide.");
            return $this->redirectToRoute('admin_login');
        }

        if ($password !== $confirmPassword) {
            $this->addFlash('register_error', 'Les mots de passe ne correspondent pas.');
            return $this->redirectToRoute('admin_login');
        }

        if (strlen($password) < 8) {
            $this->addFlash('register_error', 'Le mot de passe doit contenir au moins 8 caractères.');
            return $this->redirectToRoute('admin_login');
        }

        if (!empty($telephone) && !preg_match('/^[0-9]{8}$/', $telephone)) {
            $this->addFlash('register_error', 'Le numéro de téléphone doit contenir exactement 8 chiffres.');
            return $this->redirectToRoute('admin_login');
        }

        if ($usersRepository->findOneBy(['email' => $email])) {
            $this->addFlash('register_error', 'Cette adresse email est déjà utilisée.');
            return $this->redirectToRoute('admin_login');
        }

        // ── Créer l'utilisateur avec ROLE_USER par défaut ──
        $user = new Users();
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setEmail($email);
        $user->setTelephone($telephone ?: null);
        $user->setIsActive(true);
        $user->setCreatedAt(new \DateTime());
        $user->setUpdatedAt(new \DateTime());
        $user->setPasswordHash($hasher->hashPassword($user, $password));

        // Assigner ROLE_USER uniquement
        $roleUser = $roleRepository->findOneBy(['name' => 'ROLE_USER']);
        if ($roleUser) {
            $user->addRole($roleUser);
        }

        $em->persist($user);
        $em->flush();

        $this->addFlash('register_success', 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.');
        return $this->redirectToRoute('admin_login');
    }

    #[Route('/profile', name: 'front_profile')]
    public function profile(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_home');
        }
        return $this->render('home/profile.html.twig', ['user' => $user]);
    }
}
