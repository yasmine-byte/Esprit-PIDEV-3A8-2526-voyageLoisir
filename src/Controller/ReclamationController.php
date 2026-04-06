<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Form\ReclamationType;
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reclamation')]
class ReclamationController extends AbstractController
{
    #[Route('/', name: 'reclamation_index', methods: ['GET'])]
    public function index(ReclamationRepository $reclamationRepository): Response
    {
        $userId = 1;
        return $this->render('reclamation/index.html.twig', [
            'reclamations' => $reclamationRepository->findBy(['userId' => $userId]),
        ]);
    }

    #[Route('/new', name: 'reclamation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $reclamation = new Reclamation();
        $form        = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reclamation->setUserId(1);
            $reclamation->setDateCreation(new \DateTime());
            $reclamation->setStatut('En attente');
            $reclamation->setTypeFeedback('Négatif');

            $entityManager->persist($reclamation);
            $entityManager->flush();

            $this->addFlash('success', 'Réclamation créée et envoyée avec succès.');

            // Notification admin
            $session = $request->getSession();
            $notifs  = $session->get('admin_notifications', []);
            $priorite = $reclamation->getPriorite();
            $icon     = $priorite === 'Urgente' ? '🚨' : '📋';
            $type     = $priorite === 'Urgente' ? 'danger' : 'warning';
            $notifs[] = [
                'type'    => $type,
                'icon'    => $icon,
                'message' => 'Nouvelle réclamation : "' . mb_substr($reclamation->getTitre(), 0, 40) . '" — Priorité : ' . $priorite,
                'time'    => (new \DateTime())->format('H:i'),
            ];
            $session->set('admin_notifications', $notifs);

            return $this->redirectToRoute('reclamation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reclamation/new.html.twig', [
            'reclamation' => $reclamation,
            'form'        => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'reclamation_show', methods: ['GET'])]
    public function show(Reclamation $reclamation): Response
    {
        return $this->render('reclamation/show.html.twig', [
            'reclamation' => $reclamation,
            'avis'        => $reclamation->getAvis(),
        ]);
    }

    #[Route('/{id}/edit', name: 'reclamation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): Response
    {
        if ($reclamation->getStatut() === 'Fermée') {
            $this->addFlash('error', 'Une réclamation fermée ne peut pas être modifiée.');
            return $this->redirectToRoute('reclamation_index');
        }

        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Réclamation modifiée avec succès.');

            // Notification admin
            $session = $request->getSession();
            $notifs  = $session->get('admin_notifications', []);
            $notifs[] = [
                'type'    => 'info',
                'icon'    => '✏️',
                'message' => 'La réclamation #' . $reclamation->getId() . ' a été modifiée par le client.',
                'time'    => (new \DateTime())->format('H:i'),
            ];
            $session->set('admin_notifications', $notifs);

            return $this->redirectToRoute('reclamation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reclamation/edit.html.twig', [
            'reclamation' => $reclamation,
            'form'        => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'reclamation_delete', methods: ['POST'])]
    public function delete(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $reclamation->getId(), $request->request->get('_token'))) {
            $entityManager->remove($reclamation);
            $entityManager->flush();
            $this->addFlash('success', 'Réclamation supprimée avec succès.');
        }
        return $this->redirectToRoute('reclamation_index', [], Response::HTTP_SEE_OTHER);
    }
}