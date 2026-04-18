<?php
namespace App\Controller\Api;

use App\Service\NotificationService;
use App\Service\HebergementRecommandationService;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/notifications', name: 'api_notif_')]
class NotificationController extends AbstractController
{
    public function __construct(
        private NotificationService $notifService,
        private HebergementRecommandationService $recommandationService,
        private ReservationRepository $reservationRepo,
    ) {}

    /**
     * POST /api/notifications/reservation-confirmee
     * Body: { "fcm_token": "...", "reservation_id": 12 }
     */
    #[Route('/reservation-confirmee', name: 'resa_confirmee', methods: ['POST'])]
    public function reservationConfirmee(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $token  = $data['fcm_token'] ?? null;
        $resaId = $data['reservation_id'] ?? null;

        if (!$token || !$resaId) {
            return $this->json(['error' => 'fcm_token et reservation_id requis'], 400);
        }

        $resa = $this->reservationRepo->find($resaId);
        if (!$resa) {
            return $this->json(['error' => 'Réservation introuvable'], 404);
        }

        $ok = $this->notifService->notifyReservationConfirmee(
            $token,
            $resa->getClientNom(),
            $resa->getHebergement()?->getAdresse() ?? 'votre hébergement'
        );

        return $this->json(['success' => $ok]);
    }

    /**
     * POST /api/notifications/paiement-recu
     * Body: { "fcm_token": "...", "client_nom": "...", "montant": "150.00" }
     */
    #[Route('/paiement-recu', name: 'paiement', methods: ['POST'])]
    public function paiementRecu(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['fcm_token'])) {
            return $this->json(['error' => 'fcm_token requis'], 400);
        }

        $ok = $this->notifService->notifyPaiementRecu(
            $data['fcm_token'],
            $data['client_nom'] ?? 'Client',
            $data['montant']    ?? '0'
        );

        return $this->json(['success' => $ok]);
    }

    /**
     * GET /api/notifications/recommandations?email=client@mail.com&fcm_token=xxx
     */
    #[Route('/recommandations', name: 'recommandations', methods: ['GET'])]
    public function recommandations(Request $request): JsonResponse
    {
        $email    = $request->query->get('email');
        $fcmToken = $request->query->get('fcm_token');

        if (!$email) {
            return $this->json(['error' => 'email requis'], 400);
        }

        $hebergements = $this->recommandationService->recommanderPourClient($email);

        $result = array_map(function ($heb) use ($email) {
            return [
                'id'      => $heb->getId(),
                'adresse' => $heb->getAdresse(),
                'prix'    => $heb->getPrix(),
                'type'    => $heb->getType()?->getNom(),
                'image'   => $heb->getImagePath(),
                'score'   => $this->recommandationService->calculerScore($heb, $email),
            ];
        }, $hebergements);

        // Envoyer une push si token fourni
        if ($fcmToken && !empty($hebergements)) {
            $premier = $hebergements[0];
            $this->notifService->notifyNouvelleRecommandation(
                $fcmToken,
                explode('@', $email)[0],
                $premier->getAdresse() ?? '',
                (string) ($premier->getPrix() ?? '0')
            );
        }

        return $this->json(['recommandations' => $result]);
    }

    /**
     * POST /api/notifications/reservation-annulee
     * Body: { "fcm_token": "...", "reservation_id": 12 }
     */
    #[Route('/reservation-annulee', name: 'resa_annulee', methods: ['POST'])]
    public function reservationAnnulee(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $token  = $data['fcm_token'] ?? null;
        $resaId = $data['reservation_id'] ?? null;

        if (!$token || !$resaId) {
            return $this->json(['error' => 'fcm_token et reservation_id requis'], 400);
        }

        $resa = $this->reservationRepo->find($resaId);
        if (!$resa) {
            return $this->json(['error' => 'Réservation introuvable'], 404);
        }

        $ok = $this->notifService->notifyReservationAnnulee(
            $token,
            $resa->getClientNom(),
            $resa->getHebergement()?->getAdresse() ?? 'votre hébergement'
        );

        return $this->json(['success' => $ok]);
    }
}