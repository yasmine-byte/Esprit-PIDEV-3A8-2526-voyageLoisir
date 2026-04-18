<?php
namespace App\Service;

use App\Repository\ReservationRepository;
use App\Repository\HebergementRepository;
use App\Entity\Hebergement;

class HebergementRecommandationService
{
    public function __construct(
        private ReservationRepository $reservationRepo,
        private HebergementRepository $hebergementRepo,
    ) {}

    /**
     * Recommander des hébergements basés sur l'historique du client
     * Logique : même type, prix similaire, pas déjà réservé
     */
    public function recommanderPourClient(string $clientEmail, int $limit = 4): array
    {
        // 1. Récupérer toutes les réservations du client
        $reservations = $this->reservationRepo->findBy(['clientEmail' => $clientEmail]);

        if (empty($reservations)) {
            // Nouveau client : retourner les plus populaires
            return $this->getHebPopulaires($limit);
        }

        // 2. Analyser les préférences : types et fourchette de prix
        $typesIds      = [];
        $prixTotal     = 0;
        $hebDejaVus    = [];

        foreach ($reservations as $res) {
            $heb = $res->getHebergement();
            if (!$heb) continue;

            $hebDejaVus[] = $heb->getId();
            $prixTotal   += (float) $heb->getPrix();

            if ($heb->getType()) {
                $typesIds[] = $heb->getType()->getId();
            }
        }

        $prixMoyen  = count($reservations) > 0 ? $prixTotal / count($reservations) : 100;
        $typesFavs  = array_count_values($typesIds);
        arsort($typesFavs);
        $typePrefeId = array_key_first($typesFavs);

        // 3. Chercher des hébergements similaires
        return $this->hebergementRepo->findSimilaires(
            $typePrefeId,
            $prixMoyen,
            $hebDejaVus,
            $limit
        );
    }

    /**
     * Hébergements populaires (les plus réservés)
     */
    public function getHebPopulaires(int $limit = 4): array
    {
        return $this->hebergementRepo->findLesReserves($limit);
    }

    /**
     * Score de compatibilité (pour affichage %)
     */
    public function calculerScore(Hebergement $heb, string $clientEmail): int
    {
        $reservations = $this->reservationRepo->findBy(['clientEmail' => $clientEmail]);
        if (empty($reservations)) return 70;

        $score = 50;

        foreach ($reservations as $res) {
            $h = $res->getHebergement();
            if (!$h) continue;

            // +20 si même type
            if ($h->getType() && $heb->getType() && $h->getType()->getId() === $heb->getType()->getId()) {
                $score += 20;
            }

            // +15 si prix similaire (±30%)
            $prixRef = (float) $h->getPrix();
            $prixHeb = (float) $heb->getPrix();
            if ($prixRef > 0 && abs($prixHeb - $prixRef) / $prixRef <= 0.3) {
                $score += 15;
            }
        }

        return min(99, $score);
    }
}