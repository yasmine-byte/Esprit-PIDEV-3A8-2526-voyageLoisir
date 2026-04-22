<?php
namespace App\Controller;

use App\Repository\HebergementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class chatbotmehdiController extends AbstractController
{
    #[Route('/api/chatbot/hebergement/{id}', name: 'app_chatbot_hebergement', methods: ['POST'])]
    public function chat(
        int $id,
        Request $request,
        HebergementRepository $hebergementRepository
    ): JsonResponse {
        $hebergement = $hebergementRepository->find($id);
        if (!$hebergement) {
            return new JsonResponse(['success' => false, 'reply' => 'Hébergement non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $userMessage = $data['message'] ?? '';

        if (empty($userMessage)) {
            return new JsonResponse(['success' => false, 'reply' => 'Message vide'], 400);
        }

        $context = sprintf(
            "Tu es un assistant virtuel pour l'hébergement suivant sur le site Vianova :
            - Nom/Description : %s
            - Type : %s
            - Prix : %s TND par nuit
            - Adresse : %s
            
            Réponds en français de manière courte et utile. 
            Si on te pose des questions sur la réservation, dis à l'utilisateur de remplir le formulaire.
            Ne réponds QUE aux questions relatives à cet hébergement.",
            $hebergement->getDescription(),
            $hebergement->getType() ? $hebergement->getType()->getNom() : 'Non spécifié',
            $hebergement->getPrix(),
            $hebergement->getAdresse() ?? 'Tunisie'
        );

        $apiKey = $_ENV['OPENAI_API_KEY'] ?? '';
        if (empty($apiKey)) {
            return new JsonResponse(['success' => false, 'reply' => 'Clé API non configurée.']);
        }

        $reply = $this->callGroq($apiKey, $context, $userMessage);
        return new JsonResponse(['success' => true, 'reply' => $reply]);
    }

    private function callGroq(string $apiKey, string $context, string $userMessage): string
    {
        $payload = json_encode([
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [
                ['role' => 'system', 'content' => $context],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'max_tokens' => 300,
            'temperature' => 0.7,
        ]);

        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) return 'Erreur de connexion : ' . $error;

        $data = json_decode($result, true);
        if (isset($data['error'])) return 'Erreur API : ' . $data['error']['message'];

        return $data['choices'][0]['message']['content'] ?? 'Désolé, je ne peux pas répondre.';
    }
}