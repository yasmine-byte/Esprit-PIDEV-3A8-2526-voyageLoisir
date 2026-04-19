<?php

namespace App\Controller\Api;

use App\Service\BlogChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/chatbot')]
class ChatbotApiController extends AbstractController
{
    #[Route('/ask', name: 'api_chatbot_ask', methods: ['POST'])]
    public function ask(Request $request, BlogChatbotService $blogChatbotService): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'success' => false,
                'message' => 'Corps JSON invalide.',
            ], 400);
        }

        $message = trim((string) ($payload['message'] ?? ''));
        if ('' === $message) {
            return $this->json([
                'success' => false,
                'message' => 'Le message est obligatoire.',
            ], 422);
        }

        try {
            $result = $blogChatbotService->answer(
                $message,
                isset($payload['article_content']) ? (string) $payload['article_content'] : null
            );
        } catch (\Throwable) {
            return $this->json([
                'success' => false,
                'message' => 'Le chatbot est temporairement indisponible.',
            ], 500);
        }

        return $this->json($result);
    }
}

