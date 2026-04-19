<?php
namespace App\Service;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Psr\Log\LoggerInterface;

class NotificationService
{
    private ?object $messaging = null;

    public function __construct(
        private LoggerInterface $logger,
        private string $fcmProjectId,
    ) {}

    private function getMessaging(): object
    {
        if ($this->messaging === null) {
            $credentialsPath = $_ENV['GOOGLE_APPLICATION_CREDENTIALS'] ?? '';

            if (!str_starts_with($credentialsPath, '/') && !str_contains($credentialsPath, ':')) {
                $credentialsPath = dirname(__DIR__, 2) . '/' . $credentialsPath;
            }

            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $this->messaging = $factory->createMessaging();
        }

        return $this->messaging;
    }

    public function sendPush(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        $fcmToken = trim($fcmToken);
        if ($fcmToken === '') {
            $this->logger->warning('[FCM] Token vide, notification ignoree.');
            return false;
        }

        try {
            $messaging = $this->getMessaging();

            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification(Notification::create($title, $body))
                ->withData(array_map('strval', $data));

            $messaging->send($message);

            $this->logger->info(sprintf('[FCM][%s] Notification envoyee : %s', $this->fcmProjectId, $title));
            return true;
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('[FCM][%s] Exception : %s', $this->fcmProjectId, $e->getMessage()));
            return false;
        }
    }

    public function notifyReservationConfirmee(string $fcmToken, string $clientNom, string $hebergementAdresse): bool
    {
        return $this->sendPush(
            $fcmToken,
            'Reservation confirmee',
            "Bonjour {$clientNom}, votre sejour a {$hebergementAdresse} est confirme.",
            ['type' => 'reservation_confirmee', 'url' => '/profile']
        );
    }

    public function notifyReservationAnnulee(string $fcmToken, string $clientNom, string $hebergementAdresse): bool
    {
        return $this->sendPush(
            $fcmToken,
            'Reservation annulee',
            "Bonjour {$clientNom}, votre reservation a {$hebergementAdresse} a ete annulee.",
            ['type' => 'reservation_annulee', 'url' => '/profile']
        );
    }

    public function notifyPaiementRecu(string $fcmToken, string $clientNom, string $montant): bool
    {
        return $this->sendPush(
            $fcmToken,
            'Paiement recu',
            "Bonjour {$clientNom}, votre paiement de {$montant} DT a ete recu.",
            ['type' => 'paiement', 'url' => '/profile']
        );
    }

    public function notifyNouvelleRecommandation(string $fcmToken, string $clientNom, string $hebergementAdresse, string $prix): bool
    {
        return $this->sendPush(
            $fcmToken,
            'Recommandation pour vous',
            "Bonjour {$clientNom}, decouvrez {$hebergementAdresse} a partir de {$prix} DT/nuit.",
            ['type' => 'recommandation', 'url' => '/hebergement']
        );
    }
}
