<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Repository\RoleRepository;
use App\Repository\VoyageRepository;
use App\Service\GmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FrontSecurityController extends AbstractController
{
    #[Route('/login', name: 'front_login')]
    public function login(): Response
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
            $fieldErrors['nom'] = empty($nom) ? 'Le nom est obligatoire.' : 'Le nom doit contenir uniquement des lettres.';

        if (empty($prenom) || !preg_match('/^[A-Za-zÀ-ÿ\s]{2,50}$/', $prenom))
            $fieldErrors['prenom'] = empty($prenom) ? 'Le prénom est obligatoire.' : 'Le prénom doit contenir uniquement des lettres.';

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
            $request->getSession()->set('register_field_errors', $fieldErrors);
            $request->getSession()->set('register_form_data', compact('nom', 'prenom', 'email', 'telephone'));
            return $this->redirectToRoute('admin_login');
        }

        $user = new Users();
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setEmail($email);
        $user->setTelephone($telephone ?: null);
        $user->setIsActive(false);
        $user->setIsVerified(false);
        $user->setCreatedAt(new \DateTime());
        $user->setUpdatedAt(new \DateTime());
        $user->setPasswordHash($hasher->hashPassword($user, $password));

        // ── Token de vérification ──
        $token = bin2hex(random_bytes(32));
        $user->setVerificationToken($token);

        $roleUser = $roleRepository->findOneBy(['name' => 'ROLE_USER']);
        if ($roleUser) $user->addRole($roleUser);

        $em->persist($user);
        $em->flush();

        // ── Lien de vérification ──
        $verificationLink = $this->generateUrl(
            'verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // ── Envoi email de vérification ──
        try {
            $gmailService->sendVerificationEmail(
                $user->getEmail(),
                $user->getPrenom() . ' ' . $user->getNom(),
                $verificationLink
            );
        } catch (\Exception $e) {}

        $request->getSession()->remove('register_field_errors');
        $request->getSession()->remove('register_form_data');
        $this->addFlash('register_success', '✔ Compte créé ! Vérifiez votre email pour activer votre compte.');
        return $this->redirectToRoute('admin_login');
    }

    #[Route('/profile', name: 'front_profile')]
    public function profile(VoyageRepository $voyageRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_home');
        }

        $reservations = $voyageRepository->findByReservedUser($user);

        // ✅ Récupérer le statut paid PAR USER pour chaque voyage
        $paidVoyages = [];
        if ($user instanceof Users) {
            $rows = $entityManager->getConnection()->fetchAllAssociative(
                'SELECT voyage_id FROM voyage_reservations WHERE users_id = :uid AND paid = 1',
                ['uid' => $user->getId()]
            );
            foreach ($rows as $row) {
                $paidVoyages[$row['voyage_id']] = true;
            }
        }

        return $this->render('home/profile.html.twig', [
            'user'         => $user,
            'reservations' => $reservations,
            'paidVoyages'  => $paidVoyages,
        ]);
    }
}