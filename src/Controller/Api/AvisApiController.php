<?php

namespace App\Controller\Api;

use App\Entity\Avis;
use App\Entity\Reclamation;
use App\Entity\TypeAvis;
use App\Repository\AvisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/avis')]
class AvisApiController extends AbstractController
{
    #[Route('', name: 'api_avis_index', methods: ['GET'])]
    public function index(AvisRepository $avisRepository): JsonResponse
    {
        $avisList = $avisRepository->findAll();
        $data = [];
        
        foreach ($avisList as $avis) {
            $data[] = [
                'id' => $avis->getId(),
                'userId' => $avis->getUserId(),
                'contenu' => $avis->getContenu(),
                'nbEtoiles' => $avis->getNbEtoiles(),
                'statut' => $avis->getStatut(),
                'type' => $avis->getType() ? $avis->getType()->getNom() : null,
                'date' => $avis->getDateAvis() ? $avis->getDateAvis()->format('Y-m-d H:i:s') : null,
            ];
        }

        return $this->json($data);
    }

    #[Route('/{id}', name: 'api_avis_show', methods: ['GET'])]
    public function show(Avis $avis, EntityManagerInterface $entityManager): JsonResponse
    {
        $reclamation = $entityManager->getRepository(Reclamation::class)->findOneBy(['avis' => $avis]);

        $data = [
            'id' => $avis->getId(),
            'userId' => $avis->getUserId(),
            'contenu' => $avis->getContenu(),
            'nbEtoiles' => $avis->getNbEtoiles(),
            'statut' => $avis->getStatut(),
            'type' => $avis->getType() ? $avis->getType()->getNom() : null,
            'date' => $avis->getDateAvis() ? $avis->getDateAvis()->format('Y-m-d H:i:s') : null,
            'reclamationId' => $reclamation ? $reclamation->getId() : null,
        ];

        return $this->json($data);
    }

    #[Route('', name: 'api_avis_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): JsonResponse
    {
        $reqData = json_decode($request->getContent(), true);
        if (!$reqData) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $avis = new Avis();
        $avis->setUserId($reqData['userId'] ?? 1);
        $avis->setContenu($reqData['contenu'] ?? '');
        $avis->setNbEtoiles($reqData['nbEtoiles'] ?? 0);
        $avis->setStatut('En attente');
        $avis->setDateAvis(new \DateTime());
        
        if (isset($reqData['type_id'])) {
            $type = $entityManager->getRepository(TypeAvis::class)->find($reqData['type_id']);
            $avis->setType($type);
        }

        $errors = $validator->validate($avis);
        if (count($errors) > 0) {
            $errorMsgs = [];
            foreach ($errors as $error) {
                $errorMsgs[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMsgs], 422);
        }

        $entityManager->persist($avis);

        if ($avis->getNbEtoiles() <= 2) {
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
        }

        $entityManager->flush();

        return $this->json(['message' => 'Avis créé', 'id' => $avis->getId()], 201);
    }

    #[Route('/{id}', name: 'api_avis_update', methods: ['PUT'])]
    public function update(Request $request, Avis $avis, EntityManagerInterface $entityManager, ValidatorInterface $validator): JsonResponse
    {
        $reqData = json_decode($request->getContent(), true);

        if (isset($reqData['contenu'])) $avis->setContenu($reqData['contenu']);
        if (isset($reqData['nbEtoiles'])) $avis->setNbEtoiles($reqData['nbEtoiles']);
        if (isset($reqData['type_id'])) {
            $type = $entityManager->getRepository(TypeAvis::class)->find($reqData['type_id']);
            $avis->setType($type);
        }

        $errors = $validator->validate($avis);
        if (count($errors) > 0) {
            $errorMsgs = [];
            foreach ($errors as $error) {
                $errorMsgs[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMsgs], 422);
        }

        $entityManager->flush();

        return $this->json(['message' => 'Avis modifié']);
    }

    #[Route('/{id}', name: 'api_avis_delete', methods: ['DELETE'])]
    public function delete(Avis $avis, EntityManagerInterface $entityManager): JsonResponse
    {
        $reclamation = $entityManager->getRepository(Reclamation::class)->findOneBy(['avis' => $avis]);
        if ($reclamation) {
            $reclamation->setAvis(null);
        }

        $entityManager->remove($avis);
        $entityManager->flush();

        return $this->json(['message' => 'Avis supprimé']);
    }
}
