<?php

namespace App\Controller;

use App\Entity\CommentReport;
use App\Entity\CommentReaction;
use App\Entity\Commentaire;
use App\Entity\Users;
use App\Form\CommentaireType;
use App\Repository\CommentReactionRepository;
use App\Repository\CommentReportRepository;
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

    #[Route('/{id}/report', name: 'app_commentaire_report', methods: ['POST'])]
    public function report(
        Request $request,
        Commentaire $commentaire,
        CommentReportRepository $commentReportRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof Users) {
            return $this->json(['message' => 'Vous devez etre connecte pour signaler un commentaire.'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->isCsrfTokenValid('report_comment_' . $commentaire->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['message' => 'Jeton de requete invalide.'], Response::HTTP_FORBIDDEN);
        }

        $reason = mb_strtolower(trim((string) $request->request->get('reason', CommentReport::REASON_OTHER)));
        if (!CommentReport::isValidReason($reason)) {
            return $this->json(['message' => 'Raison de signalement invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existing = $commentReportRepository->findOneByCommentAndUser($commentaire, $user);
        if ($existing instanceof CommentReport) {
            return $this->json([
                'success' => true,
                'alreadyReported' => true,
                'message' => 'Vous avez deja signale ce commentaire.',
            ]);
        }

        $report = (new CommentReport())
            ->setCommentaire($commentaire)
            ->setUser($user)
            ->setReason($reason)
            ->setStatus(CommentReport::STATUS_PENDING)
            ->setCreatedAt(new \DateTime())
            ->setUpdatedAt(null);

        $entityManager->persist($report);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'alreadyReported' => false,
            'message' => 'Commentaire signale avec succes.',
        ]);
    }

    #[Route('/{id}/react', name: 'app_commentaire_react', methods: ['POST'])]
    public function react(
        Request $request,
        Commentaire $commentaire,
        CommentReactionRepository $commentReactionRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof Users) {
            return $this->json(['message' => 'Vous devez etre connecte pour reagir.'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->isCsrfTokenValid('react_comment_' . $commentaire->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['message' => 'Jeton de requete invalide.'], Response::HTTP_FORBIDDEN);
        }

        $allowedTypes = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];
        $reactionType = mb_strtolower(trim((string) $request->request->get('reaction_type')));
        if (!in_array($reactionType, $allowedTypes, true)) {
            return $this->json(['message' => 'Reaction invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existing = $commentReactionRepository->findOneByCommentAndUser($commentaire, $user);
        $userReaction = $reactionType;

        if ($existing instanceof CommentReaction) {
            if ($existing->getReactionType() === $reactionType) {
                $entityManager->remove($existing);
                $userReaction = null;
            } else {
                $existing->setReactionType($reactionType);
                $existing->setUpdatedAt(new \DateTime());
            }
        } else {
            $reaction = (new CommentReaction())
                ->setCommentaire($commentaire)
                ->setUser($user)
                ->setReactionType($reactionType)
                ->setCreatedAt(new \DateTime())
                ->setUpdatedAt(new \DateTime());
            $entityManager->persist($reaction);
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'counts' => $commentReactionRepository->aggregateCountsForComment($commentaire),
            'userReaction' => $userReaction,
        ]);
    }
}
