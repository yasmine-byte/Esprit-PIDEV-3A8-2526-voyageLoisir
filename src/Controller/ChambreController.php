<?php
namespace App\Controller;

use App\Entity\Chambre;
use App\Entity\Hebergement;
use App\Form\ChambreType;
use App\Repository\ChambreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/chambre')]
final class ChambreController extends AbstractController
{
    #[Route(name: 'app_chambre_index', methods: ['GET'])]
    public function index(Request $request, ChambreRepository $chambreRepository): Response
    {
        $query = $request->query->get('q');
        $typeChambre = $request->query->get('type');
        $chambres = $chambreRepository->search($query, $typeChambre);

        return $this->render('chambre/index.html.twig', [
            'chambres' => $chambres,
            'q' => $query,
            'typeFilter' => $typeChambre,
        ]);
    }

    #[Route('/new', name: 'app_chambre_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $chambre = new Chambre();
        $hebergementId = $request->query->get('hebergement');
        if ($hebergementId) {
            $hebergement = $entityManager->getRepository(Hebergement::class)->find($hebergementId);
            if ($hebergement) $chambre->setHebergement($hebergement);
        }

        $form = $this->createForm(ChambreType::class, $chambre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($chambre);
            $entityManager->flush();
            $this->addFlash('success', 'Chambre ajoutée avec succès !');
            return $this->redirectToRoute('app_hebergement_show', ['id' => $chambre->getHebergement()->getId()]);
        }

        return $this->render('chambre/new.html.twig', ['chambre' => $chambre, 'form' => $form]);
    }

    #[Route('/{id}', name: 'app_chambre_show', methods: ['GET'])]
    public function show(Chambre $chambre): Response
    {
        return $this->render('chambre/show.html.twig', ['chambre' => $chambre]);
    }

    #[Route('/{id}/edit', name: 'app_chambre_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Chambre $chambre, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ChambreType::class, $chambre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Chambre modifiée avec succès !');
            return $this->redirectToRoute('app_hebergement_show', ['id' => $chambre->getHebergement()->getId()]);
        }

        return $this->render('chambre/edit.html.twig', ['chambre' => $chambre, 'form' => $form]);
    }

    #[Route('/{id}', name: 'app_chambre_delete', methods: ['POST'])]
    public function delete(Request $request, Chambre $chambre, EntityManagerInterface $entityManager): Response
    {
        $hebergementId = $chambre->getHebergement()?->getId();
        if ($this->isCsrfTokenValid('delete'.$chambre->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($chambre);
            $entityManager->flush();
            $this->addFlash('success', 'Chambre supprimée avec succès !');
        }
        if ($hebergementId) return $this->redirectToRoute('app_hebergement_show', ['id' => $hebergementId]);
        return $this->redirectToRoute('app_chambre_index', [], Response::HTTP_SEE_OTHER);
    }
}