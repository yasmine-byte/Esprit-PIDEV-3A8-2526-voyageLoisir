<?php

namespace App\Controller\Api;

use App\Entity\Blog;
use App\Service\BlogExcerptGeneratorService;
use App\Service\BlogTranslationService;
use App\Service\CommentModerationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/blogs')]
class BlogApiController extends AbstractController
{
    #[Route('/translate', name: 'api_blog_translate', methods: ['POST'])]
    public function translate(Request $request, BlogTranslationService $blogTranslationService): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Corps JSON invalide.'], 400);
        }

        $result = $blogTranslationService->translate(
            (string) ($payload['text'] ?? ''),
            (string) ($payload['target_language'] ?? ''),
            isset($payload['source_language']) ? (string) $payload['source_language'] : 'auto'
        );

        if (!($result['success'] ?? false)) {
            return $this->json($result, 422);
        }

        return $this->json($result);
    }

    #[Route('/{id}/translate', name: 'api_blog_translate_article', methods: ['POST'])]
    public function translateArticle(Blog $blog, Request $request, BlogTranslationService $blogTranslationService): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Corps JSON invalide.'], 400);
        }

        $targetLanguage = (string) ($payload['target_language'] ?? '');
        $sourceLanguage = isset($payload['source_language']) ? (string) $payload['source_language'] : 'auto';

        $translatedTitle = $blogTranslationService->translate((string) $blog->getTitre(), $targetLanguage, $sourceLanguage);
        $translatedExcerpt = $blogTranslationService->translate((string) ($blog->getExtrait() ?? ''), $targetLanguage, $sourceLanguage);
        $translatedContent = $blogTranslationService->translate((string) $blog->getContenu(), $targetLanguage, $sourceLanguage);

        if (!($translatedTitle['success'] ?? false) || !($translatedContent['success'] ?? false)) {
            return $this->json([
                'success' => false,
                'message' => $translatedContent['message'] ?? $translatedTitle['message'] ?? 'Impossible de traduire l article.',
                'details' => [
                    'title' => $translatedTitle,
                    'excerpt' => $translatedExcerpt,
                    'content' => $translatedContent,
                ],
            ], 422);
        }

        return $this->json([
            'success' => true,
            'blog_id' => $blog->getId(),
            'source_language' => $translatedContent['source_language'] ?? $sourceLanguage,
            'target_language' => $targetLanguage,
            'translated' => [
                'title' => $translatedTitle['translated_text'] ?? $blog->getTitre(),
                'excerpt' => $translatedExcerpt['translated_text'] ?? $blog->getExtrait(),
                'content' => $translatedContent['translated_text'] ?? $blog->getContenu(),
            ],
        ]);
    }

    #[Route('/comments/moderate', name: 'api_blog_comment_moderate', methods: ['POST'])]
    public function moderateComment(Request $request, CommentModerationService $commentModerationService): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Corps JSON invalide.'], 400);
        }

        return $this->json($commentModerationService->moderate((string) ($payload['content'] ?? '')));
    }

    #[Route('/generate-excerpt', name: 'api_blog_generate_excerpt', methods: ['POST'])]
    public function generateExcerpt(Request $request, BlogExcerptGeneratorService $blogExcerptGeneratorService): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Corps JSON invalide.'], 400);
        }

        $excerpt = $blogExcerptGeneratorService->generate(
            (string) ($payload['title'] ?? ''),
            (string) ($payload['content'] ?? ''),
            (int) ($payload['max_length'] ?? 180)
        );

        if ('' === $excerpt) {
            return $this->json(['message' => 'Le contenu est obligatoire pour generer un extrait.'], 422);
        }

        return $this->json([
            'success' => true,
            'excerpt' => $excerpt,
        ]);
    }
}
