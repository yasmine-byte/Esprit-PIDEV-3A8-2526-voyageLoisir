<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Entity\Reclamation;
use App\Form\AvisType;
use App\Repository\AvisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/avis')]
class AvisController extends AbstractController
{
    #[Route('/', name: 'avis_index', methods: ['GET'])]
    public function index(AvisRepository $avisRepository): Response
    {
        $userId = 1;
        
        return $this->render('avis/index.html.twig', [
            'avis' => $avisRepository->findBy(['userId' => $userId]),
        ]);
    }

    #[Route('/new', name: 'avis_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $avis = new Avis();
        $form = $this->createForm(AvisType::class, $avis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avis->setUserId(1);
            $avis->setDateAvis(new \DateTime());
            $avis->setStatut('En attente');

            $entityManager->persist($avis);

            if ($avis->getNbEtoiles() <= 2) {
                // Generate a reclamation automatically
                $reclamation = new Reclamation();
                $reclamation->setAvis($avis);
                $reclamation->setContenu($avis->getContenu());
                $reclamation->setTypeFeedback('Négatif');
                $reclamation->setStatut('En attente');
                $reclamation->setPriorite('Moyenne');
                $reclamation->setTitre('Réclamation automatique suite à un avis négatif');
                $reclamation->setUserId($avis->getUserId());
                $reclamation->setType($avis->getType());
                $reclamation->setDateCreation(new \DateTime());
                
                $entityManager->persist($reclamation);
                
                $this->addFlash('warning', 'Votre avis négatif a généré une réclamation automatique.');
            } else {
                $this->addFlash('success', 'Votre avis a été soumis avec succès.');
            }

            $entityManager->flush();

            return $this->redirectToRoute('avis_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('avis/new.html.twig', [
            'avis' => $avis,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'avis_show', methods: ['GET'])]
    public function show(Avis $avis, EntityManagerInterface $entityManager): Response
    {
        $reclamation = $entityManager->getRepository(Reclamation::class)->findOneBy(['avis' => $avis]);
        
        return $this->render('avis/show.html.twig', [
            'avis' => $avis,
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/{id}/edit', name: 'avis_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Avis $avis, EntityManagerInterface $entityManager): Response
    {
        if ($avis->getStatut() !== 'En attente') {
            $this->addFlash('error', 'Seuls les avis "En attente" peuvent être modifiés.');
            return $this->redirectToRoute('avis_index');
        }

        $form = $this->createForm(AvisType::class, $avis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Avis modifié avec succès.');
            return $this->redirectToRoute('avis_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('avis/edit.html.twig', [
            'avis' => $avis,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'avis_delete', methods: ['POST'])]
    public function delete(Request $request, Avis $avis, EntityManagerInterface $entityManager): Response
    {
        if ($avis->getStatut() !== 'En attente') {
            $this->addFlash('error', 'Seuls les avis "En attente" peuvent être supprimés.');
            return $this->redirectToRoute('avis_index');
        }

        if ($this->isCsrfTokenValid('delete'.$avis->getId(), $request->request->get('_token'))) {
            $reclamation = $entityManager->getRepository(Reclamation::class)->findOneBy(['avis' => $avis]);
            if ($reclamation) {
                $reclamation->setAvis(null);
            }
            $entityManager->remove($avis);
            $entityManager->flush();
            $this->addFlash('success', 'Avis supprimé avec succès.');
        }

        return $this->redirectToRoute('avis_index', [], Response::HTTP_SEE_OTHER);
    }
}
