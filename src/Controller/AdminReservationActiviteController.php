<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Entity\ReservationActivite;
use App\Form\ReservationActiviteType;
use App\Repository\ReservationActiviteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reservation/activite')]
final class AdminReservationActiviteController extends AbstractController
{
    #[Route('/', name: 'admin_reservation_activite_index', methods: ['GET'])]
    public function index(Request $request, ReservationActiviteRepository $reservationActiviteRepository): Response
    {
        $activiteId = $request->query->get('activite');

        if ($activiteId) {
            $reservationActivites = $reservationActiviteRepository->findBy([
                'activite' => $activiteId,
            ]);
        } else {
            $reservationActivites = $reservationActiviteRepository->findAll();
        }

        return $this->render('admin/reservation_activite/index.html.twig', [
            'reservation_activites' => $reservationActivites,
            'activite_id' => $activiteId,
        ]);
    }

    #[Route('/new/{id}', name: 'admin_reservation_activite_new', methods: ['GET', 'POST'])]
    public function new(
        Activite $activite,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $reservationActivite = new ReservationActivite();
        $reservationActivite->setActivite($activite);
        $reservationActivite->setStatut('EN_ATTENTE');

        $form = $this->createForm(ReservationActiviteType::class, $reservationActivite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $prix = $reservationActivite->getActivite()?->getPrix() ?? 0;
            $nb = $reservationActivite->getNombrePersonnes() ?? 0;
            $reservationActivite->setTotal($prix * $nb);

            $entityManager->persist($reservationActivite);
            $entityManager->flush();

            return $this->redirectToRoute('admin_reservation_activite_index', [
                'activite' => $activite->getId(),
            ]);
        }

        return $this->render('admin/reservation_activite/new.html.twig', [
            'reservation_activite' => $reservationActivite,
            'form' => $form->createView(),
            'activite' => $activite,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_reservation_activite_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        ReservationActivite $reservationActivite,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(ReservationActiviteType::class, $reservationActivite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $prix = $reservationActivite->getActivite()?->getPrix() ?? 0;
            $nb = $reservationActivite->getNombrePersonnes() ?? 0;
            $reservationActivite->setTotal($prix * $nb);

            $entityManager->flush();

            return $this->redirectToRoute('admin_reservation_activite_index');
        }

        return $this->render('admin/reservation_activite/edit.html.twig', [
            'reservation_activite' => $reservationActivite,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_reservation_activite_show', methods: ['GET'])]
    public function show(ReservationActivite $reservationActivite): Response
    {
        return $this->render('admin/reservation_activite/show.html.twig', [
            'reservation_activite' => $reservationActivite,
        ]);
    }

    #[Route('/{id}', name: 'admin_reservation_activite_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        ReservationActivite $reservationActivite,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $reservationActivite->getId(), $request->request->get('_token'))) {
            $entityManager->remove($reservationActivite);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_reservation_activite_index');
    }
}