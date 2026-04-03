<?php
namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\HebergementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReservationController extends AbstractController
{
    #[Route('/reservation/{id}', name: 'app_reservation_new', methods: ['POST'])]
    public function new(int $id, Request $request, EntityManagerInterface $em, HebergementRepository $repo): Response
    {
        $hebergement = $repo->find($id);

        $dateDebut = new \DateTime($request->request->get('dateDebut'));
        $dateFin = new \DateTime($request->request->get('dateFin'));
        $nbNuits = $dateDebut->diff($dateFin)->days;
        $total = $nbNuits * $hebergement->getPrix();

        $reservation = new Reservation();
        $reservation->setHebergement($hebergement);
        $reservation->setClientNom($request->request->get('clientNom'));
        $reservation->setClientTel($request->request->get('clientTel'));
        $reservation->setClientEmail($request->request->get('clientEmail'));
        $reservation->setDateDebut($dateDebut);
        $reservation->setDateFin($dateFin);
        $reservation->setNbNuits($nbNuits);
        $reservation->setTotal((string)$total);
        $reservation->setStatut('en_attente');
        $reservation->setCreatedAt(new \DateTime());

        $em->persist($reservation);
        $em->flush();

        $this->addFlash('success', 'Votre réservation a été confirmée ! Nous vous contacterons bientôt.');

        return $this->redirectToRoute('app_property_details', ['id' => $id]);
    }
}