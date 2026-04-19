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

        $events = [];

        foreach ($reservationActivites as $reservation) {
            $color = '#3788d8';

            if ($reservation->getStatut() === 'CONFIRMEE') {
                $color = '#1f8b4c';
            } elseif ($reservation->getStatut() === 'EN_ATTENTE') {
                $color = '#d89b5b';
            } elseif ($reservation->getStatut() === 'ANNULEE') {
                $color = '#d9534f';
            }

            $events[] = [
                'title' => $reservation->getActivite()
                    ? $reservation->getActivite()->getNom() . ' (' . $reservation->getNombrePersonnes() . ' pers)'
                    : 'Réservation #' . $reservation->getId(),
                'start' => $reservation->getDateReservation()
                    ? $reservation->getDateReservation()->format('Y-m-d')
                    : null,
                'color' => $color,
            ];
        }

        $todayCount = 0;
        $pendingCount = 0;
        $confirmedCount = 0;
        $cancelledCount = 0;

        $today = new \DateTime();
        $todayStr = $today->format('Y-m-d');

        foreach ($reservationActivites as $reservation) {
            if (
                $reservation->getDateReservation() &&
                $reservation->getDateReservation()->format('Y-m-d') === $todayStr
            ) {
                $todayCount++;
            }

            if ($reservation->getStatut() === 'EN_ATTENTE') {
                $pendingCount++;
            } elseif ($reservation->getStatut() === 'CONFIRMEE') {
                $confirmedCount++;
            } elseif ($reservation->getStatut() === 'ANNULEE') {
                $cancelledCount++;
            }
        }

        $totalReservations = count($reservationActivites);

        $aiSummary = $this->generateReservationSummary(
            $totalReservations,
            $pendingCount,
            $todayCount,
            $confirmedCount,
            $cancelledCount
        );

        // Si aucun ?activite=... n'est fourni, on prend l'id de la première activité disponible
        if (!$activiteId && !empty($reservationActivites) && $reservationActivites[0]->getActivite()) {
            $activiteId = $reservationActivites[0]->getActivite()->getId();
        }

        return $this->render('admin/reservation_activite/index.html.twig', [
            'reservation_activites' => $reservationActivites,
            'activite_id' => $activiteId,
            'events' => $events,
            'todayCount' => $todayCount,
            'pendingCount' => $pendingCount,
            'aiSummary' => $aiSummary,
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

            $reservationActivite->setUser($this->getUser());

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

    private function generateReservationSummary(
    int $totalReservations,
    int $pendingCount,
    int $todayCount,
    int $confirmedCount,
    int $cancelledCount
): string {
    $apiKey = $_ENV['OPENROUTER_API_KEY'] ?? $_SERVER['OPENROUTER_API_KEY'] ?? '';

    if (!$apiKey) {
        return "Résumé IA indisponible : clé API manquante.";
    }

    $severity = 'normal';
    $priorityMessage = 'Situation stable.';

    if ($pendingCount >= 5) {
        $severity = 'critique';
        $priorityMessage = "Priorité critique : volume élevé de réservations en attente.";
    } elseif ($pendingCount >= 2) {
        $severity = 'attention';
        $priorityMessage = "Attention : plusieurs réservations en attente nécessitent un traitement rapide.";
    } elseif ($pendingCount === 1) {
        $severity = 'suivi';
        $priorityMessage = "Une réservation en attente doit être traitée.";
    }

    if ($todayCount > 0 && $pendingCount > 0) {
        $priorityMessage .= " Des réservations prévues aujourd'hui imposent un suivi immédiat.";
    }

    $prompt = "Tu es un assistant administratif pour une plateforme touristique.

Rédige un résumé détaillé et professionnel pour un tableau de bord admin des réservations.
Contraintes :
- écrire en français
- ton formel, administratif et clair
- longueur : 5 à 7 phrases
- ne rien inventer
- utiliser uniquement les données fournies
- inclure une conclusion opérationnelle pour l'équipe admin

Niveau d'alerte : $severity

Constat métier :
$priorityMessage

Données exactes :
- Nombre total de réservations : $totalReservations
- Réservations en attente : $pendingCount
- Réservations confirmées : $confirmedCount
- Réservations annulées : $cancelledCount
- Réservations prévues aujourd'hui : $todayCount

Le résumé doit :
- décrire la situation générale
- commenter le volume en attente
- commenter les confirmations et annulations
- indiquer si la journée nécessite une attention particulière
- terminer par une recommandation administrative claire.";

    $data = [
        "model" => "openai/gpt-3.5-turbo",
        "messages" => [
            [
                "role" => "user",
                "content" => $prompt
            ]
        ],
        "temperature" => 0.4
    ];

    $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json",
        "HTTP-Referer: http://127.0.0.1:8000",
        "X-Title: Vianova Admin Reservations Summary"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return "Résumé IA indisponible pour le moment.";
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if (!isset($result['choices'][0]['message']['content'])) {
        return "Aucun résumé généré.";
    }

    return trim($result['choices'][0]['message']['content']);
}
}