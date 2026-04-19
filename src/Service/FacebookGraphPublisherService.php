<?php

namespace App\Service;

use App\Entity\Blog;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FacebookGraphPublisherService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $facebookPageId,
        private readonly string $facebookPageAccessToken,
        private readonly string $facebookGraphVersion,
        private readonly string $publicBaseUrl,
    ) {
    }

    public function publishBlog(Blog $blog): array
    {
        $pageId = trim($this->facebookPageId);
        $accessToken = trim($this->facebookPageAccessToken);
        if ('' === $pageId || '' === $accessToken) {
            return [
                'success' => false,
                'message' => 'Configuration Facebook manquante (page ID ou token).',
                'error_code' => 'missing_config',
            ];
        }

        $shareUrl = rtrim($this->publicBaseUrl, '/') . '/blog/' . $blog->getId();
        $excerpt = trim((string) ($blog->getExtrait() ?: ''));
        if ('' === $excerpt) {
            $excerpt = trim(mb_substr(strip_tags((string) $blog->getContenu()), 0, 180));
        }

        $message = trim(sprintf("%s\n\n%s", (string) $blog->getTitre(), $excerpt));
        $endpoint = sprintf(
            'https://graph.facebook.com/%s/%s/feed',
            rawurlencode($this->facebookGraphVersion),
            rawurlencode($pageId)
        );

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'body' => [
                    'message' => $message,
                    'link' => $shareUrl,
                    'access_token' => $accessToken,
                ],
            ]);
        } catch (TransportExceptionInterface $exception) {
            return [
                'success' => false,
                'message' => 'Erreur reseau vers Facebook Graph API.',
                'error_code' => 'transport_error',
                'details' => $exception->getMessage(),
            ];
        }

        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);

        if ($statusCode >= 400 || isset($payload['error'])) {
            $error = $payload['error'] ?? [];
            return [
                'success' => false,
                'message' => (string) ($error['message'] ?? 'Publication Facebook echouee.'),
                'error_code' => (string) ($error['code'] ?? 'api_error'),
                'error_type' => (string) ($error['type'] ?? ''),
                'status_code' => $statusCode,
            ];
        }

        return [
            'success' => true,
            'post_id' => (string) ($payload['id'] ?? ''),
            'message' => 'Publication Facebook effectuee.',
            'shared_url' => $shareUrl,
        ];
    }
}
