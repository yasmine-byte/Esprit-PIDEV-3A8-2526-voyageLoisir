<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class GenerateDescriptionController extends AbstractController
{
    #[Route('/api/generate-description', name: 'app_generate_description', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $prompt = $data['prompt'] ?? '';

        if (empty($prompt)) {
            return new JsonResponse(['error' => 'Prompt vide'], 400);
        }

        $apiKey = getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? '');

        if (empty($apiKey)) {
            return new JsonResponse(['error' => 'Clé API non configurée'], 500);
        }

        $payload = json_encode([
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Tu es un expert en marketing hôtelier. Tu génères des descriptions courtes, professionnelles et attrayantes pour des hébergements touristiques en Tunisie. Réponds uniquement avec la description, sans introduction ni explication.'
                ],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 200,
            'temperature' => 0.8,
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
            return new JsonResponse(['error' => 'Erreur curl: ' . $error], 500);
        }

        $response = json_decode($result, true);

        if (isset($response['error'])) {
            return new JsonResponse(['error' => $response['error']['message']], 500);
        }

        $description = $response['choices'][0]['message']['content'] ?? '';

        return new JsonResponse(['description' => trim($description)]);
    }
}