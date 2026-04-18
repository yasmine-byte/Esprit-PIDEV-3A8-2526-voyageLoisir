<?php

namespace App\Service;

class TelegramService
{
    private string $token = '8693184181:AAFeekPd8xy98C7Q8cvxydbZScUZ7-YJhJ4';
    private string $apiUrl;

    public function __construct()
    {
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}/sendMessage";
    }

    /**
     * Envoie un message a un seul chat_id.
     */
    public function send(string $chatId, string $message): void
    {
        if (empty(trim($chatId))) return;

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POSTFIELDS     => http_build_query([
                'chat_id'    => $chatId,
                'text'       => $message,
                'parse_mode' => 'HTML',
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Envoie un message a plusieurs users en meme temps.
     * Chaque user doit avoir un telegramChatId non nul.
     */
    public function sendToMany(array $users, string $message): void
    {
        foreach ($users as $user) {
            $chatId = method_exists($user, 'getTelegramChatId')
                ? $user->getTelegramChatId()
                : null;

            if ($chatId && trim($chatId) !== '') {
                $this->send($chatId, $message);
            }
        }
    }

    // ----------------------------------------------------------------
    // CAS 1 — Nouvelle destination => tous les users
    // ----------------------------------------------------------------
    public function notifyNewDestination(array $users, string $nom, string $pays, string $saison): void
    {
        $message = "🌍 <b>VoyageLoisir — Nouvelle destination !</b>\n\n"
                 . "📍 <b>{$nom}</b> — {$pays}\n"
                 . "🌤 Meilleure saison : {$saison}\n\n"
                 . "Connectez-vous pour réserver votre voyage !";

        $this->sendToMany($users, $message);
    }

    // ----------------------------------------------------------------
    // CAS 2 — Destination inactive => users ayant reservé un voyage lié
    // ----------------------------------------------------------------
    public function notifyDestinationInactive(array $users, string $nom): void
    {
        $message = "⚠️ <b>VoyageLoisir — Destination indisponible</b>\n\n"
                 . "La destination <b>{$nom}</b> est temporairement indisponible.\n"
                 . "Votre voyage associé pourrait être affecté.\n\n"
                 . "Connectez-vous pour plus d'informations.";

        $this->sendToMany($users, $message);
    }

    // ----------------------------------------------------------------
    // CAS 3 — Admin annule reservation => cet user uniquement
    // ----------------------------------------------------------------
    public function notifyReservationCancelled(string $chatId, string $depart, string $arrivee, string $dateDepart): void
    {
        $message = "❌ <b>VoyageLoisir — Réservation annulée</b>\n\n"
                 . "Votre réservation pour le voyage\n"
                 . "<b>{$depart} → {$arrivee}</b>\n"
                 . "prévu le <b>{$dateDepart}</b>\n"
                 . "a été annulée par l'administrateur.\n\n"
                 . "Contactez-nous pour plus d'informations.";

        $this->send($chatId, $message);
    }
}