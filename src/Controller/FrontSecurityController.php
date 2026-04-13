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
use App\Service\GmailService;


class FrontSecurityController extends AbstractController
{
    #[Route('/login', name: 'front_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }
        return $this->redirectToRoute('admin_login');
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
    RoleRepository $roleRepository,
    GmailService $gmailService
): Response {
    $nom             = trim($request->request->get('nom', ''));
    $prenom          = trim($request->request->get('prenom', ''));
    $email           = trim($request->request->get('email', ''));
    $telephone       = trim($request->request->get('telephone', ''));
    $password        = $request->request->get('password', '');
    $confirmPassword = $request->request->get('confirm_password', '');

    $fieldErrors = [];

    if (empty($nom) || !preg_match('/^[A-Za-zÀ-ÿ\s]{2,50}$/', $nom))
        $fieldErrors['nom'] = empty($nom) ? 'Le nom est obligatoire.' : 'Le nom doit contenir uniquement des lettres (2-50 caractères).';

    if (empty($prenom) || !preg_match('/^[A-Za-zÀ-ÿ\s]{2,50}$/', $prenom))
        $fieldErrors['prenom'] = empty($prenom) ? 'Le prénom est obligatoire.' : 'Le prénom doit contenir uniquement des lettres (2-50 caractères).';

    if (empty($email))
        $fieldErrors['email'] = "L'adresse email est obligatoire.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $fieldErrors['email'] = "Le format de l'adresse email est invalide.";
    elseif ($usersRepository->findOneBy(['email' => $email]))
        $fieldErrors['email'] = 'Cette adresse email est déjà utilisée.';

    if (!empty($telephone) && !preg_match('/^[0-9]{8}$/', $telephone))
        $fieldErrors['telephone'] = 'Le téléphone doit contenir exactement 8 chiffres.';

    if (empty($password))
        $fieldErrors['password'] = 'Le mot de passe est obligatoire.';
    elseif (strlen($password) < 6)
        $fieldErrors['password'] = 'Le mot de passe doit contenir au moins 6 caractères.';
    elseif (!preg_match('/[A-Z]/', $password))
        $fieldErrors['password'] = 'Le mot de passe doit contenir au moins une majuscule.';
    elseif (!preg_match('/[a-z]/', $password))
        $fieldErrors['password'] = 'Le mot de passe doit contenir au moins une minuscule.';
    elseif (!preg_match('/[0-9]/', $password))
        $fieldErrors['password'] = 'Le mot de passe doit contenir au moins un chiffre.';
    elseif (!preg_match('/[@$!%*?&#+\-_=.]/', $password))
        $fieldErrors['password'] = 'Le mot de passe doit contenir au moins un caractère spécial.';

    if ($password !== $confirmPassword)
        $fieldErrors['confirm_password'] = 'Les mots de passe ne correspondent pas.';

    if (!empty($fieldErrors)) {
        // Stocker les erreurs ET les données dans la session
        $request->getSession()->set('register_field_errors', $fieldErrors);
        $request->getSession()->set('register_form_data', compact('nom', 'prenom', 'email', 'telephone'));
        return $this->redirectToRoute('admin_login');
    }

    $user = new Users();
    $user->setNom($nom);
    $user->setPrenom($prenom);
    $user->setEmail($email);
    $user->setTelephone($telephone ?: null);
    $user->setIsActive(true);
    $user->setCreatedAt(new \DateTime());
    $user->setUpdatedAt(new \DateTime());
    $user->setPasswordHash($hasher->hashPassword($user, $password));

    $roleUser = $roleRepository->findOneBy(['name' => 'ROLE_USER']);
    if ($roleUser) $user->addRole($roleUser);

    $em->persist($user);
    $em->flush();
    $em->persist($user);
$em->flush();

// ── Envoi email de bienvenue ──
$gmailService->sendWelcomeEmail(
    $user->getEmail(),  // ← email de l'utilisateur inscrit
    $user->getPrenom() . ' ' . $user->getNom()
);

$this->addFlash('register_success', 'Compte créé avec succès !');
return $this->redirectToRoute('admin_login');

    $request->getSession()->remove('register_field_errors');
    $request->getSession()->remove('register_form_data');
    $this->addFlash('register_success', 'Compte créé avec succès !');
    return $this->redirectToRoute('admin_login');
}

    #[Route('/profile', name: 'front_profile')]
    public function profile(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('admin_login');
        }
        return $this->render('home/profile.html.twig', ['user' => $user]);
    }
}
