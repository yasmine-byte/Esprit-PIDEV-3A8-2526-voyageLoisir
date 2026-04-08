<?php
namespace App\Controller;
use App\Entity\Voyage;
use App\Form\VoyageType;
use App\Repository\VoyageRepository;
use App\Repository\DestinationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/voyage")]
final class VoyageController extends AbstractController
{
    #[Route(name: "app_voyage_index", methods: ["GET"])]
    public function index(Request $request, VoyageRepository $voyageRepository): Response
    {
        $search = $request->query->get("search", "");
        $tri    = $request->query->get("tri", "id");
        $ordre  = $request->query->get("ordre", "ASC");
        $voyages = $voyageRepository->findByFilters($search, $tri, $ordre);
        return $this->render("voyage/index.html.twig", [
            "voyages" => $voyages,
            "search"  => $search,
            "tri"     => $tri,
            "ordre"   => $ordre,
        ]);
    }

    #[Route("/new", name: "app_voyage_new", methods: ["GET", "POST"])]
    public function new(Request $request, EntityManagerInterface $entityManager, DestinationRepository $destRepo): Response
    {
        $voyage = new Voyage();

        $destinationId = $request->query->getInt('destination_id');
        if ($destinationId) {
            $destination = $destRepo->find($destinationId);
            if ($destination) {
                $voyage->setDestination($destination);
            }
        }

        $form = $this->createForm(VoyageType::class, $voyage);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if ($user) {
                $voyage->setCreatedBy($user);
            }
            $entityManager->persist($voyage);
            $entityManager->flush();
            return $this->redirectToRoute("app_voyage_index", [], Response::HTTP_SEE_OTHER);
        }
        return $this->render("voyage/new.html.twig", [
            "voyage"         => $voyage,
            "form"           => $form,
            "destination_id" => $destinationId,
        ]);
    }

    #[Route("/{id}", name: "app_voyage_show", methods: ["GET"])]
    public function show(Voyage $voyage): Response
    {
        return $this->render("voyage/show.html.twig", [
            "voyage" => $voyage,
        ]);
    }

    #[Route("/{id}/edit", name: "app_voyage_edit", methods: ["GET", "POST"])]
    public function edit(Request $request, Voyage $voyage, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(VoyageType::class, $voyage);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->redirectToRoute("app_voyage_index", [], Response::HTTP_SEE_OTHER);
        }
        return $this->render("voyage/edit.html.twig", [
            "voyage" => $voyage,
            "form"   => $form,
        ]);
    }

    #[Route("/{id}", name: "app_voyage_delete", methods: ["POST"])]
    public function delete(Request $request, Voyage $voyage, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid("delete" . $voyage->getId(), $request->getPayload()->getString("_token"))) {
            $entityManager->remove($voyage);
            $entityManager->flush();
        }
        return $this->redirectToRoute("app_voyage_index", [], Response::HTTP_SEE_OTHER);
    }
}
