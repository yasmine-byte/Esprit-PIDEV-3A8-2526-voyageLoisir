<?php

namespace App\Controller;

use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

class FaceController extends AbstractController
{
    #[Route('/face/register', name: 'face_register', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function registerFace(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Non connecté'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $descriptor = $data['descriptor'] ?? null;

        if (!$descriptor || count($descriptor) !== 128) {
            return $this->json(['success' => false, 'message' => 'Descriptor invalide'], 400);
        }

        $user->setFaceDescriptor($descriptor);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Visage enregistré avec succès']);
    }

    #[Route('/face/login', name: 'face_login', methods: ['POST'])]
    public function loginByFace(
        Request $request,
        UsersRepository $usersRepository,
        Security $security
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $descriptor = $data['descriptor'] ?? null;

        if (!$descriptor || count($descriptor) !== 128) {
            return $this->json(['success' => false, 'message' => 'Descriptor invalide'], 400);
        }

        $users = $usersRepository->findAll();
        $bestMatch = null;
        $bestDistance = PHP_FLOAT_MAX;

        foreach ($users as $user) {
            $stored = $user->getFaceDescriptor();
            if (!$stored || count($stored) !== 128) continue;

            $distance = $this->calcEuclideanDistance($descriptor, $stored);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestMatch = $user;
            }
        }

        if ($bestMatch && $bestDistance < 0.6) {
            $security->login($bestMatch, 'security.authenticator.form_login.front');
            return $this->json(['success' => true, 'redirect' => '/']);
        }

        return $this->json(['success' => false, 'message' => 'Visage non reconnu']);
    }

    private function calcEuclideanDistance(array $d1, array $d2): float
    {
        $sum = 0.0;
        for ($i = 0; $i < 128; $i++) {
            $sum += ($d1[$i] - $d2[$i]) ** 2;
        }
        return sqrt($sum);
    }

    #[Route('/face/register-page', name: 'face_register_page', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function registerPage(): Response
    {
        return $this->render('face/register_face.html.twig');
    }

    #[Route('/face/login-page', name: 'face_login_page', methods: ['GET'])]
    public function loginPage(): Response
    {
        return $this->render('face/login_face.html.twig');
    }
}