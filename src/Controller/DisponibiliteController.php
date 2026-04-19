<?php
namespace App\Controller;

use App\Repository\DisponibiliteRepository;
use App\Repository\ReservationRepository;
use App\Repository\HebergementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DisponibiliteController extends AbstractController
{
    // ── API Calendrier ──────────────────────────────────────────────────────────
    #[Route('/api/hebergement/{id}/calendrier', name: 'api_hebergement_calendrier', methods: ['GET'])]
    public function calendrier(
        int $id,
        HebergementRepository $hebergementRepo,
        DisponibiliteRepository $dispoRepo,
        ReservationRepository $reservationRepo
    ): JsonResponse {
        $hebergement = $hebergementRepo->find($id);
        if (!$hebergement) {
            return $this->json(['error' => 'Hébergement introuvable'], 404);
        }

        $events = [];

        // ✅ Disponibilités (vert)
        $disponibilites = $dispoRepo->findBy(['hebergement' => $hebergement, 'disponible' => true]);
        foreach ($disponibilites as $dispo) {
            $events[] = [
                'id'              => 'dispo-' . $dispo->getId(),
                'title'           => '✅ Disponible',
                'start'           => $dispo->getDateDebut()?->format('Y-m-d'),
                'end'             => $dispo->getDateFin()?->format('Y-m-d'),
                'color'           => '#22c55e',
                'textColor'       => '#fff',
                'type'            => 'disponible',
                'display'         => 'background',
            ];
        }

        // ❌ Réservations confirmées (rouge)
        $reservations = $reservationRepo->findBy([
            'hebergement' => $hebergement,
            'statut'      => 'confirmee',
        ]);
        foreach ($reservations as $resa) {
            $events[] = [
                'id'        => 'resa-' . $resa->getId(),
                'title'     => '❌ Réservé',
                'start'     => $resa->getDateDebut()?->format('Y-m-d'),
                'end'       => $resa->getDateFin()?->format('Y-m-d'),
                'color'     => '#ef4444',
                'textColor' => '#fff',
                'type'      => 'reserve',
            ];
        }

        // ⏳ Réservations en attente (orange)
        $reservationsAttente = $reservationRepo->findBy([
            'hebergement' => $hebergement,
            'statut'      => 'en_attente',
        ]);
        foreach ($reservationsAttente as $resa) {
            $events[] = [
                'id'        => 'attente-' . $resa->getId(),
                'title'     => '⏳ En attente',
                'start'     => $resa->getDateDebut()?->format('Y-m-d'),
                'end'       => $resa->getDateFin()?->format('Y-m-d'),
                'color'     => '#f59e0b',
                'textColor' => '#fff',
                'type'      => 'attente',
            ];
        }

        return $this->json($events);
    }
}