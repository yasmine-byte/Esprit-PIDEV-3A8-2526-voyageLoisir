<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Repository\RoleRepository;
use App\Service\GmailService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class GoogleController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google')]
    public function connectGoogle(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry->getClient('google')->redirect(['email', 'profile']);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectGoogleCheck(
        Request $request,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $em,
        UsersRepository $usersRepository,
        RoleRepository $roleRepository,
        UserPasswordHasherInterface $passwordHasher,
        EventDispatcherInterface $eventDispatcher,
        GmailService $gmailService
    ): Response {
        $client = $clientRegistry->getClient('google');

        try {
            // ── Désactiver SSL pour le développement local ──
            $provider = $client->getOAuth2Provider();
            $provider->setHttpClient(
                new \GuzzleHttp\Client(['verify' => false])
            );

            $googleUser = $client->fetchUser();
            $email      = $googleUser->getEmail();
            $user       = $usersRepository->findOneBy(['email' => $email]);

            // ── Créer le compte si inexistant ──
            if (!$user) {
                $user = new Users();
                $user->setNom($googleUser->getLastName() ?? 'Google');
                $user->setPrenom($googleUser->getFirstName() ?? 'User');
                $user->setEmail($email);
                $user->setIsActive(true);
                $user->setCreatedAt(new \DateTime());
                $user->setUpdatedAt(new \DateTime());
                $user->setPasswordHash(
                    $passwordHasher->hashPassword($user, bin2hex(random_bytes(16)))
                );

                $roleUser = $roleRepository->findOneBy(['name' => 'ROLE_USER']);
                if ($roleUser) {
                    $user->addRole($roleUser);
                }

                $em->persist($user);
                $em->flush();

                // Email de bienvenue
                try {
                    $gmailService->sendWelcomeEmail(
                        $email,
                        $user->getPrenom() . ' ' . $user->getNom()
                    );
                } catch (\Exception $e) {}
            }

            // ── Connecter l'utilisateur manuellement ──
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->container->get('security.token_storage')->setToken($token);
            $request->getSession()->set('_security_main', serialize($token));

            // ── Déclencher l'événement de login ──
            $event = new InteractiveLoginEvent($request, $token);
            $eventDispatcher->dispatch($event, 'security.interactive_login');

            return $this->redirectToRoute('app_home');

        } catch (IdentityProviderException $e) {
            $this->addFlash('register_error', 'Erreur OAuth : ' . $e->getMessage());
            return $this->redirectToRoute('admin_login');
        } catch (\Exception $e) {
            $this->addFlash('register_error', 'Erreur : ' . $e->getMessage());
            return $this->redirectToRoute('admin_login');
        }
    }
}