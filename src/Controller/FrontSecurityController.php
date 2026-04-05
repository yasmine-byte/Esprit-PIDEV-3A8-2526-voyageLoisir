<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\VoyageRepository;
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
        $nom             = $request->request->get('nom');
        $prenom          = $request->request->get('prenom');
        $email           = $request->request->get('email');
        $telephone       = $request->request->get('telephone');
        $password        = $request->request->get('password');
        $confirmPassword = $request->request->get('confirm_password');

        // Validations
        if ($password !== $confirmPassword) {
            $this->addFlash('register_error', 'Les mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_home');
        }

        if (strlen($password) < 8) {
            $this->addFlash('register_error', 'Le mot de passe doit contenir au moins 8 caractères.');
            return $this->redirectToRoute('app_home');
        }

        if ($usersRepository->findOneBy(['email' => $email])) {
            $this->addFlash('register_error', 'Cet email est déjà utilisé.');
            return $this->redirectToRoute('app_home');
        }

        // Créer l'utilisateur
        $user = new Users();
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setEmail($email);
        $user->setTelephone($telephone ?: null);
        $user->setIsActive(true);
        $user->setCreatedAt(new \DateTime());
        $user->setUpdatedAt(new \DateTime());
        $user->setPasswordHash($hasher->hashPassword($user, $password));

        // Attribuer ROLE_USER par défaut
        $roleUser = $roleRepository->findOneBy(['name' => 'ROLE_USER']);
        if ($roleUser) {
            $user->addRole($roleUser);
        }

        $em->persist($user);
        $em->flush();

        $this->addFlash('register_success', 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.');
        return $this->redirectToRoute('app_home');
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
