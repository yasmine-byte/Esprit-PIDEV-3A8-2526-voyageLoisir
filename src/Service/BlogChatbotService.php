<?php

namespace App\Service;

use App\Entity\Blog;
use App\Repository\BlogRepository;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BlogChatbotService
{
    private const OPENAI_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const CONTEXT_LIMIT = 12;

    public function __construct(
        private readonly BlogRepository $blogRepository,
        private readonly BlogRecommendationService $blogRecommendationService,
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $openAiApiKey = null,
        private readonly string $openAiModel = 'gpt-4o-mini',
    ) {
    }

    public function answer(string $message, ?string $articleContent = null): array
    {
        $message = trim($message);
        $articleContent = trim(strip_tags((string) $articleContent));
        if ('' === $message) {
            return [
                'success' => false,
                'reply' => 'Merci d ecrire votre question avant d envoyer.',
                'source' => 'local',
            ];
        }

        $blogs = array_slice($this->blogRepository->findPublishedOrdered(), 0, self::CONTEXT_LIMIT);
        $blogContext = $this->buildBlogContext($blogs, $articleContent);
        $localReply = $this->buildLocalReply($message, $blogs, $blogContext['recommendations'], $articleContent);

        $apiKey = trim((string) $this->openAiApiKey);
        if ('' === $apiKey) {
            return [
                'success' => true,
                'reply' => $localReply ?? 'Je peux vous aider sur les articles du blog. Essayez: "recommande-moi des articles" ou "resume l article <titre>".',
                'source' => 'local',
            ];
        }

        try {
            $response = $this->httpClient->request('POST', self::OPENAI_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->openAiModel,
                    'temperature' => 0.4,
                    'max_tokens' => 500,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu es un assistant de blog en francais. Reponds de facon claire et utile. Priorise les informations du contexte fourni. Tu peux resumer, recommander des articles et aider l utilisateur a naviguer.',
                        ],
                        [
                            'role' => 'system',
                            'content' => $blogContext['prompt_context'],
                        ],
                        [
                            'role' => 'user',
                            'content' => $message,
                        ],
                    ],
                ],
            ]);
        } catch (TransportExceptionInterface) {
            return [
                'success' => true,
                'reply' => $localReply ?? 'Le service IA est temporairement indisponible. Je peux quand meme vous aider avec des recommandations de blog.',
                'source' => 'local',
            ];
        }

        $payload = $response->toArray(false);
        $content = trim((string) ($payload['choices'][0]['message']['content'] ?? ''));
        if ('' === $content) {
            return [
                'success' => true,
                'reply' => $localReply ?? 'Je n ai pas pu generer une reponse complete. Pouvez-vous reformuler votre question ?',
                'source' => 'local',
            ];
        }

        return [
            'success' => true,
            'reply' => $content,
            'source' => 'openai',
        ];
    }

    /**
     * @param Blog[] $blogs
     */
    private function buildBlogContext(array $blogs, string $articleContent = ''): array
    {
        $metrics = $this->blogRecommendationService->buildMetrics($blogs);
        $recommendationRows = [];
        $contextRows = [];

        $ranked = $blogs;
        usort($ranked, static fn (Blog $a, Blog $b): int => ($metrics[$b->getId()]['score'] ?? 0) <=> ($metrics[$a->getId()]['score'] ?? 0));
        foreach (array_slice($ranked, 0, 5) as $index => $blog) {
            if (null === $blog->getId()) {
                continue;
            }
            $recommendationRows[] = sprintf(
                '%d) %s (score: %s)',
                $index + 1,
                (string) $blog->getTitre(),
                (string) ($metrics[$blog->getId()]['score'] ?? '0')
            );
        }

        foreach ($blogs as $blog) {
            if (null === $blog->getId()) {
                continue;
            }

            $excerpt = trim((string) ($blog->getExtrait() ?: ''));
            if ('' === $excerpt) {
                $excerpt = trim(mb_substr(strip_tags((string) $blog->getContenu()), 0, 180));
            }

            $contextRows[] = sprintf(
                '- id:%d | titre:%s | slug:%s | extrait:%s | note:%s | vues:%s | categorie:%s',
                $blog->getId(),
                (string) $blog->getTitre(),
                (string) ($blog->getSlug() ?? ''),
                $excerpt,
                (string) ($metrics[$blog->getId()]['rating_average'] ?? 0),
                (string) ($metrics[$blog->getId()]['views'] ?? 0),
                (string) ($metrics[$blog->getId()]['category'] ?? 'General')
            );
        }

        $articleContext = '';
        if ('' !== $articleContent) {
            $articleContext = "\n\nContenu de l article courant a prioriser pour les demandes de resume:\n"
                . mb_substr($articleContent, 0, 3500);
        }

        return [
            'recommendations' => $ranked,
            'prompt_context' => "Contexte blog disponible:\n" . implode("\n", $contextRows) . "\n\nTop recommandations:\n" . implode("\n", $recommendationRows) . $articleContext,
        ];
    }

    /**
     * @param Blog[] $blogs
     */
    private function buildLocalReply(string $message, array $blogs, array $recommendations, string $articleContent = ''): ?string
    {
        $normalized = mb_strtolower($message);

        if (str_contains($normalized, 'resume') || str_contains($normalized, 'résumé')) {
            if ('' !== $articleContent) {
                return 'Resume de cet article: ' . $this->summarizeCurrentArticleLocal($articleContent);
            }

            $target = $this->findBestMatchingBlog($normalized, $blogs);
            if ($target instanceof Blog) {
                $excerpt = trim((string) ($target->getExtrait() ?: mb_substr(strip_tags((string) $target->getContenu()), 0, 220)));

                return sprintf(
                    'Resume de "%s": %s',
                    (string) $target->getTitre(),
                    $excerpt
                );
            }
        }

        if (str_contains($normalized, 'recommend') || str_contains($normalized, 'conseil') || str_contains($normalized, 'article')) {
            $lines = [];
            foreach (array_slice($recommendations, 0, 3) as $blog) {
                if (!$blog instanceof Blog || null === $blog->getId()) {
                    continue;
                }
                $lines[] = sprintf(
                    '- %s (/%s) : %s',
                    (string) $blog->getTitre(),
                    'blog/' . $blog->getId(),
                    trim((string) ($blog->getExtrait() ?: mb_substr(strip_tags((string) $blog->getContenu()), 0, 120)))
                );
            }

            if ([] !== $lines) {
                return "Voici des recommandations de lecture:\n" . implode("\n", $lines);
            }
        }

        if (str_contains($normalized, 'aide') || str_contains($normalized, 'help')) {
            return 'Je peux: 1) resumer un article, 2) recommander des blogs, 3) expliquer ou trouver un sujet dans les articles disponibles.';
        }

        return null;
    }

    /**
     * @param Blog[] $blogs
     */
    private function findBestMatchingBlog(string $normalizedMessage, array $blogs): ?Blog
    {
        foreach ($blogs as $blog) {
            $title = mb_strtolower(trim((string) $blog->getTitre()));
            if ('' !== $title && str_contains($normalizedMessage, $title)) {
                return $blog;
            }
        }

        foreach ($blogs as $blog) {
            $slug = mb_strtolower(trim((string) $blog->getSlug()));
            if ('' !== $slug && str_contains($normalizedMessage, $slug)) {
                return $blog;
            }
        }

        return $blogs[0] ?? null;
    }

    private function summarizeCurrentArticleLocal(string $articleContent): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $articleContent) ?? $articleContent);
        if ('' === $text) {
            return 'Le contenu de l article est vide.';
        }

        $sentences = preg_split('/(?<=[\.\!\?\;])\s+/u', $text) ?: [];
        $summary = [];
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ('' === $sentence) {
                continue;
            }

            $summary[] = $sentence;
            if (count($summary) >= 3 || mb_strlen(implode(' ', $summary)) >= 320) {
                break;
            }
        }

        $result = trim(implode(' ', $summary));
        if ('' === $result) {
            $result = mb_substr($text, 0, 320);
        }

        if (mb_strlen($result) > 360) {
            $result = rtrim(mb_substr($result, 0, 357)) . '...';
        }

        return $result;
    }
}

