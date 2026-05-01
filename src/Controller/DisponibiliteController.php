<?php
namespace App\Controller;

use App\Repository\DisponibiliteRepository;
use App\Repository\ReservationRepository;
use App\Repository\HebergementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class DisponibiliteController extends AbstractController
{
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

        // Récupérer toutes les réservations (confirmées + en attente)
        $reservationsConfirmees = $reservationRepo->findBy([
            'hebergement' => $hebergement,
            'statut'      => 'confirmee',
        ]);
        $reservationsAttente = $reservationRepo->findBy([
            'hebergement' => $hebergement,
            'statut'      => 'en_attente',
        ]);

        $toutesReservations = array_merge($reservationsConfirmees, $reservationsAttente);

        // ✅ Disponibilités — divisées autour des réservations
        $disponibilites = $dispoRepo->findBy(['hebergement' => $hebergement, 'disponible' => true]);

        foreach ($disponibilites as $dispo) {
            $dispoDebut = clone $dispo->getDateDebut();
            $dispoFin   = clone $dispo->getDateFin();

            // Construire les périodes disponibles en soustrayant les réservations
            $periodesDispo = [['debut' => $dispoDebut, 'fin' => $dispoFin]];

            foreach ($toutesReservations as $resa) {
                $resaDebut = $resa->getDateDebut();
                $resaFin   = $resa->getDateFin();

                $nouvellesPeriodes = [];
                foreach ($periodesDispo as $periode) {
                    // Pas de chevauchement → garder la période
                    if ($resaFin <= $periode['debut'] || $resaDebut >= $periode['fin']) {
                        $nouvellesPeriodes[] = $periode;
                        continue;
                    }
                    // Chevauchement → diviser en deux
                    // Partie avant la réservation
                    if ($periode['debut'] < $resaDebut) {
                        $nouvellesPeriodes[] = [
                            'debut' => clone $periode['debut'],
                            'fin'   => clone $resaDebut,
                        ];
                    }
                    // Partie après la réservation
                    if ($resaFin < $periode['fin']) {
                        $nouvellesPeriodes[] = [
                            'debut' => clone $resaFin,
                            'fin'   => clone $periode['fin'],
                        ];
                    }
                }
                $periodesDispo = $nouvellesPeriodes;
            }

            // Ajouter les périodes disponibles restantes
            foreach ($periodesDispo as $periode) {
                if ($periode['debut'] < $periode['fin']) {
                    $events[] = [
                        'id'        => 'dispo-' . $dispo->getId() . '-' . $periode['debut']->format('Ymd'),
                        'title'     => '✅ Disponible',
                        'start'     => $periode['debut']->format('Y-m-d'),
                        'end'       => $periode['fin']->format('Y-m-d'),
                        'color'     => '#22c55e',
                        'textColor' => '#fff',
                        'display'   => 'background',
                    ];
                }
            }
        }

        // ❌ Réservations confirmées (rouge)
        foreach ($reservationsConfirmees as $resa) {
            $events[] = [
                'id'        => 'resa-' . $resa->getId(),
                'title'     => '❌ Réservé',
                'start'     => $resa->getDateDebut()?->format('Y-m-d'),
                'end'       => $resa->getDateFin()?->format('Y-m-d'),
                'color'     => '#ef4444',
                'textColor' => '#fff',
            ];
        }

        // ⏳ Réservations en attente (orange)
        foreach ($reservationsAttente as $resa) {
            $events[] = [
                'id'        => 'attente-' . $resa->getId(),
                'title'     => '⏳ En attente',
                'start'     => $resa->getDateDebut()?->format('Y-m-d'),
                'end'       => $resa->getDateFin()?->format('Y-m-d'),
                'color'     => '#f59e0b',
                'textColor' => '#fff',
            ];
        }

        return $this->json($events);
    }
}