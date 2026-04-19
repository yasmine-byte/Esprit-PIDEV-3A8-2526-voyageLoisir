<?php
namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\HebergementRepository;
use App\Repository\DisponibiliteRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReservationController extends AbstractController
{
    public function __construct(private NotificationService $notifService) {}

    #[Route('/reservation/{id}', name: 'app_reservation_new', methods: ['POST'])]
    public function new(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        HebergementRepository $repo,
        DisponibiliteRepository $disponibiliteRepo
    ): Response {
        $hebergement = $repo->find($id);

        $dateDebut = new \DateTime($request->request->get('dateDebut'));
        $dateFin   = new \DateTime($request->request->get('dateFin'));

        if ($dateFin <= $dateDebut) {
            $this->addFlash('error', 'La date de départ doit être après la date d\'arrivée.');
            return $this->redirectToRoute('app_property_details', ['id' => $id]);
        }

        $disponibilites = $disponibiliteRepo->findBy([
            'hebergement' => $hebergement,
            'disponible'  => true,
        ]);

        $estDisponible = false;
        foreach ($disponibilites as $dispo) {
            if ($dateDebut >= $dispo->getDateDebut() && $dateFin <= $dispo->getDateFin()) {
                $estDisponible = true;
                break;
            }
        }

        if (!$estDisponible) {
            $this->addFlash('error', 'Cet hébergement n\'est pas disponible pour les dates choisies.');
            return $this->redirectToRoute('app_property_details', ['id' => $id]);
        }

        $reservationsExistantes = $em->getRepository(Reservation::class)->createQueryBuilder('r')
            ->where('r.hebergement = :hebergement')
            ->andWhere('r.statut != :annulee')
            ->andWhere('r.dateDebut < :dateFin AND r.dateFin > :dateDebut')
            ->setParameter('hebergement', $hebergement)
            ->setParameter('annulee', 'annulee')
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->getQuery()
            ->getResult();

        if (count($reservationsExistantes) > 0) {
            $this->addFlash('error', 'Cet hébergement est déjà réservé pour ces dates.');
            return $this->redirectToRoute('app_property_details', ['id' => $id]);
        }

        $nbNuits = $dateDebut->diff($dateFin)->days;

        // ✅ Utiliser le prix de la chambre sélectionnée si disponible
        $prixParNuit = (float) ($request->request->get('prixParNuit') ?: $hebergement->getPrix());
        $total       = $nbNuits * $prixParNuit;

        $reservation = new Reservation();
        $reservation->setHebergement($hebergement);
        $reservation->setClientNom($request->request->get('clientNom'));
        $reservation->setClientTel($request->request->get('clientTel'));
        $reservation->setClientEmail($request->request->get('clientEmail'));
        $reservation->setDateDebut($dateDebut);
        $reservation->setDateFin($dateFin);
        $reservation->setNbNuits($nbNuits);
        $reservation->setTotal((string) $total);
        $reservation->setStatut('en_attente');
        $reservation->setCreatedAt(new \DateTime());

        $em->persist($reservation);
        $em->flush();

        // Notification FCM si token présent
        $fcmToken = $request->request->get('fcm_token');
        if ($fcmToken) {
            $this->notifService->notifyReservationConfirmee(
                $fcmToken,
                $reservation->getClientNom(),
                $reservation->getHebergement()->getAdresse() ?? 'votre hébergement'
            );
        }

        return $this->redirectToRoute('app_reservation_recap', ['id' => $reservation->getId()]);
    }

    #[Route('/reservation/recap/{id}', name: 'app_reservation_recap', methods: ['GET'])]
    public function recap(int $id, EntityManagerInterface $em): Response
    {
        $reservation = $em->getRepository(Reservation::class)->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée.');
        }

        return $this->render('reservation/recap.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}