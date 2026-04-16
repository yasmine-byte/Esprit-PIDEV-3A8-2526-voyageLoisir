<?php
namespace App\Controller;

use App\Repository\HebergementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ChatbotController extends AbstractController
{
    #[Route('/api/chatbot/{id}', name: 'app_chatbot', methods: ['POST'])]
    public function chat(
        int $id,
        Request $request,
        HebergementRepository $hebergementRepository
    ): JsonResponse {
        $hebergement = $hebergementRepository->find($id);

        if (!$hebergement) {
            return new JsonResponse(['error' => 'Hébergement non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $userMessage = $data['message'] ?? '';

        if (empty($userMessage)) {
            return new JsonResponse(['error' => 'Message vide'], 400);
        }

        $context = sprintf(
            "Tu es un assistant virtuel pour l'hébergement suivant sur le site Vianova :
            - Nom/Description : %s
            - Type : %s
            - Prix : %s TND par nuit
            - Nombre de chambres : %d
            - Adresse : %s
            
            Réponds en français de manière courte et utile. Si on te pose des questions sur la réservation, 
            dis à l'utilisateur de remplir le formulaire à droite. Ne réponds qu'aux questions relatives 
            à cet hébergement ou aux voyages en général.",
            $hebergement->getDescription(),
            $hebergement->getType() ? $hebergement->getType()->getNom() : 'Non spécifié',
            $hebergement->getPrix(),
            $hebergement->getNo()->count(),
            $hebergement->getAdresse() ?? 'Tunisie'
        );

        $apiKey = getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? '');

        if (empty($apiKey)) {
            return new JsonResponse(['response' => 'Clé API non configurée.']);
        }

        $response = $this->callGroq($apiKey, $context, $userMessage);

        return new JsonResponse(['response' => $response]);
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

        if ($error) {
            return 'Erreur de connexion : ' . $error;
        }

        $data = json_decode($result, true);

        if (isset($data['error'])) {
            return 'Erreur API : ' . $data['error']['message'];
        }

        return $data['choices'][0]['message']['content'] ?? 'Désolé, je ne peux pas répondre pour le moment.';
    }
}