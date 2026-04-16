<?php

namespace App\Controller;

use App\Entity\Commentaire;
use App\Form\CommentaireType;
use App\Repository\CommentaireRepository;
use App\Service\CommentModerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commentaire')]
final class CommentaireController extends AbstractController
{
    #[Route(name: 'app_commentaire_index', methods: ['GET'])]
    public function index(CommentaireRepository $commentaireRepository): Response
    {
        return $this->render('commentaire/index.html.twig', [
            'commentaires' => $commentaireRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_commentaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, CommentModerationService $commentModerationService): Response
    {
        $commentaire = new Commentaire();
        $form = $this->createForm(CommentaireType::class, $commentaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $moderation = $commentModerationService->moderate((string) $commentaire->getContenu());
            $commentaire->setContenu($moderation['sanitized'] ?? (string) $commentaire->getContenu());
            $entityManager->persist($commentaire);
            $entityManager->flush();

            return $this->redirectToRoute('app_commentaire_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('commentaire/new.html.twig', [
            'commentaire' => $commentaire,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_commentaire_show', methods: ['GET'])]
    public function show(Commentaire $commentaire): Response
    {
        return $this->render('commentaire/show.html.twig', [
            'commentaire' => $commentaire,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_commentaire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Commentaire $commentaire, EntityManagerInterface $entityManager, CommentModerationService $commentModerationService): Response
    {
        $form = $this->createForm(CommentaireType::class, $commentaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $moderation = $commentModerationService->moderate((string) $commentaire->getContenu());
            $commentaire->setContenu($moderation['sanitized'] ?? (string) $commentaire->getContenu());
            $entityManager->flush();

            return $this->redirectToRoute('app_commentaire_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('commentaire/edit.html.twig', [
            'commentaire' => $commentaire,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_commentaire_delete', methods: ['POST'])]
    public function delete(Request $request, Commentaire $commentaire, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$commentaire->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($commentaire);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_commentaire_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/like', name: 'app_commentaire_like', methods: ['POST'])]
    public function like(Request $request, Commentaire $commentaire, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->isCsrfTokenValid('like_comment_' . $commentaire->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['message' => 'Invalid like token.'], Response::HTTP_FORBIDDEN);
        }

        $session = $request->getSession();
        $key = sprintf('comment_liked_%d', $commentaire->getId());

        if ($session->has($key)) {
            return $this->json([
                'likesCount' => $commentaire->getLikesCount() ?? 0,
                'alreadyLiked' => true,
            ]);
        }

        $commentaire->setLikesCount(($commentaire->getLikesCount() ?? 0) + 1);
        $entityManager->flush();

        $session->set($key, true);

        return $this->json([
            'likesCount' => $commentaire->getLikesCount() ?? 0,
            'alreadyLiked' => false,
        ]);
    }
}
