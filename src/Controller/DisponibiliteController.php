<?php
namespace App\Controller;

use App\Entity\Disponibilite;
use App\Form\DisponibiliteType;
use App\Repository\DisponibiliteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/disponibilite')]
final class DisponibiliteController extends AbstractController
{
    #[Route(name: 'app_disponibilite_index', methods: ['GET'])]
    public function index(Request $request, DisponibiliteRepository $disponibiliteRepository): Response
    {
        $query = $request->query->get('q');
        $statut = $request->query->get('statut');
        $disponibilites = $disponibiliteRepository->search($query, $statut);

        return $this->render('disponibilite/index.html.twig', [
            'disponibilites' => $disponibilites,
            'q' => $query,
            'statut' => $statut,
        ]);
    }

    #[Route('/new', name: 'app_disponibilite_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $disponibilite = new Disponibilite();
        $form = $this->createForm(DisponibiliteType::class, $disponibilite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($disponibilite);
            $entityManager->flush();
            $this->addFlash('success', 'Disponibilité ajoutée avec succès !');
            return $this->redirectToRoute('app_disponibilite_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('disponibilite/new.html.twig', ['disponibilite' => $disponibilite, 'form' => $form]);
    }

    #[Route('/{id}', name: 'app_disponibilite_show', methods: ['GET'])]
    public function show(Disponibilite $disponibilite): Response
    {
        return $this->render('disponibilite/show.html.twig', ['disponibilite' => $disponibilite]);
    }

    #[Route('/{id}/edit', name: 'app_disponibilite_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Disponibilite $disponibilite, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DisponibiliteType::class, $disponibilite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Disponibilité modifiée avec succès !');
            return $this->redirectToRoute('app_disponibilite_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('disponibilite/edit.html.twig', ['disponibilite' => $disponibilite, 'form' => $form]);
    }

    #[Route('/{id}', name: 'app_disponibilite_delete', methods: ['POST'])]
    public function delete(Request $request, Disponibilite $disponibilite, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$disponibilite->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($disponibilite);
            $entityManager->flush();
            $this->addFlash('success', 'Disponibilité supprimée avec succès !');
        }
        return $this->redirectToRoute('app_disponibilite_index', [], Response::HTTP_SEE_OTHER);
    }
}