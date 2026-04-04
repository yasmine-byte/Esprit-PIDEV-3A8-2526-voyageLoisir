<?php

namespace App\Controller\Api;

use App\Entity\Reclamation;
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/reclamations')]
class ReclamationApiController extends AbstractController
{
    #[Route('', name: 'api_reclamation_index', methods: ['GET'])]
    public function index(ReclamationRepository $reclamationRepository): JsonResponse
    {
        $reclamations = $reclamationRepository->findAll();
        $data = [];
        
        foreach ($reclamations as $rec) {
            $data[] = [
                'id' => $rec->getId(),
                'titre' => $rec->getTitre(),
                'statut' => $rec->getStatut(),
                'priorite' => $rec->getPriorite(),
                'type' => $rec->getType() ? $rec->getType()->getNom() : null,
                'date' => $rec->getDateCreation() ? $rec->getDateCreation()->format('Y-m-d H:i:s') : null,
            ];
        }

        return $this->json($data);
    }

    #[Route('/{id}', name: 'api_reclamation_show', methods: ['GET'])]
    public function show(Reclamation $reclamation): JsonResponse
    {
        $data = [
            'id' => $reclamation->getId(),
            'titre' => $reclamation->getTitre(),
            'contenu' => $reclamation->getContenu(),
            'typeFeedback' => $reclamation->getTypeFeedback(),
            'statut' => $reclamation->getStatut(),
            'priorite' => $reclamation->getPriorite(),
            'type' => $reclamation->getType() ? $reclamation->getType()->getNom() : null,
            'date' => $reclamation->getDateCreation() ? $reclamation->getDateCreation()->format('Y-m-d H:i:s') : null,
            'avis' => $reclamation->getAvis() ? [
                'id' => $reclamation->getAvis()->getId(),
                'contenu' => $reclamation->getAvis()->getContenu(),
                'nbEtoiles' => $reclamation->getAvis()->getNbEtoiles()
            ] : null,
        ];

        return $this->json($data);
    }

    #[Route('/{id}/statut', name: 'api_reclamation_statut', methods: ['PUT'])]
    public function changeStatut(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): JsonResponse
    {
        $reqData = json_decode($request->getContent(), true);
        
        if (isset($reqData['statut'])) {
            $validStatuts = ['En attente', 'En cours', 'Traitée', 'Fermée'];
            if (in_array($reqData['statut'], $validStatuts)) {
                $reclamation->setStatut($reqData['statut']);
                $entityManager->flush();
                return $this->json(['message' => 'Statut mis à jour']);
            } else {
                return $this->json(['error' => 'Statut invalide'], 400); 
            }
        }
        
        return $this->json(['error' => 'Veuillez fournir le champ statut'], 400);
    }
}
