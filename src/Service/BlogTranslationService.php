<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BlogTranslationService
{
    private const SUPPORTED_LANGUAGES = ['ar', 'de', 'en', 'es', 'fr', 'it'];
    private const DEFAULT_PUBLIC_PROVIDER_URL = 'https://api.mymemory.translated.net/get';
    private const PUBLIC_CHUNK_LENGTH = 450;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $providerUrl = null,
        private readonly ?string $providerApiKey = null,
        private readonly float $timeout = 8.0,
    ) {
    }

    public function translate(string $text, string $targetLanguage, ?string $sourceLanguage = 'auto'): array
    {
        $text = trim($text);
        $targetLanguage = strtolower(trim($targetLanguage));
        $sourceLanguage = $sourceLanguage ? strtolower(trim($sourceLanguage)) : 'auto';

        if ('' === $text) {
            return [
                'success' => false,
                'message' => 'Text is required for translation.',
            ];
        }

        if (!in_array($targetLanguage, self::SUPPORTED_LANGUAGES, true)) {
            return [
                'success' => false,
                'message' => 'Unsupported target language.',
                'supported_languages' => self::SUPPORTED_LANGUAGES,
            ];
        }

        $detectedLanguage = 'auto' === $sourceLanguage ? $this->detectLanguage($text) : $sourceLanguage;
        if ($detectedLanguage === $targetLanguage) {
            return [
                'success' => true,
                'translated_text' => $text,
                'source_language' => $detectedLanguage,
                'target_language' => $targetLanguage,
                'provider' => 'no-op',
                'message' => 'Source and target languages are the same.',
            ];
        }

        try {
            return $this->translateInChunks($text, $targetLanguage, $sourceLanguage, $detectedLanguage);
        } catch (ExceptionInterface|\Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Translation request failed.',
                'error' => $exception->getMessage(),
                'source_language' => $detectedLanguage,
                'target_language' => $targetLanguage,
                'provider' => 'remote',
            ];
        }
    }

    public function detectLanguage(string $text): string
    {
        $normalized = mb_strtolower(trim($text));
        if ('' === $normalized) {
            return 'auto';
        }

        if (preg_match('/[\x{0600}-\x{06FF}]/u', $normalized)) {
            return 'ar';
        }

        $scores = [
            'fr' => [' le ', ' la ', ' les ', ' de ', ' des ', ' une ', ' et ', ' avec ', ' pour ', ' que '],
            'en' => [' the ', ' and ', ' with ', ' this ', ' that ', ' from ', ' for ', ' you ', ' your ', ' is '],
            'es' => [' el ', ' la ', ' los ', ' las ', ' una ', ' para ', ' con ', ' que ', ' por ', ' del '],
            'de' => [' der ', ' die ', ' das ', ' und ', ' mit ', ' ist ', ' nicht ', ' ein ', ' eine ', ' zu '],
            'it' => [' il ', ' lo ', ' la ', ' gli ', ' una ', ' con ', ' per ', ' che ', ' del ', ' nel '],
        ];

        $bestLanguage = 'en';
        $bestScore = -1;
        $haystack = ' ' . $normalized . ' ';

        foreach ($scores as $language => $markers) {
            $score = 0;
            foreach ($markers as $marker) {
                $score += substr_count($haystack, $marker);
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestLanguage = $language;
            }
        }

        return $bestLanguage;
    }

    /**
     * @return string[]
     */
    public function getSupportedLanguages(): array
    {
        return self::SUPPORTED_LANGUAGES;
    }

    private function translateInChunks(string $text, string $targetLanguage, string $sourceLanguage, string $detectedLanguage): array
    {
        $translatedChunks = [];
        $provider = $this->resolveProvider();
        $resolvedSource = $detectedLanguage;

        foreach ($this->splitIntoChunks($text) as $chunk) {
            $chunk = trim($chunk);
            if ('' === $chunk) {
                continue;
            }

            $result = 'mymemory' === $provider['type']
                ? $this->translateWithMyMemory($chunk, $targetLanguage, $sourceLanguage, $detectedLanguage, $provider['url'])
                : $this->translateWithRemoteProvider($chunk, $targetLanguage, $sourceLanguage, $detectedLanguage, $provider['url']);

            if (!($result['success'] ?? false)) {
                return $result;
            }

            $translatedChunks[] = trim((string) ($result['translated_text'] ?? ''));
            $resolvedSource = (string) ($result['source_language'] ?? $resolvedSource);
        }

        return [
            'success' => true,
            'translated_text' => implode("\n\n", array_filter($translatedChunks, static fn (string $chunk): bool => '' !== $chunk)),
            'source_language' => $resolvedSource,
            'target_language' => $targetLanguage,
            'provider' => $provider['label'],
        ];
    }

    private function resolveProvider(): array
    {
        $providerUrl = trim((string) $this->providerUrl);
        if ('' === $providerUrl) {
            return [
                'type' => 'mymemory',
                'url' => self::DEFAULT_PUBLIC_PROVIDER_URL,
                'label' => 'mymemory-public',
            ];
        }

        if (str_contains($providerUrl, 'mymemory.translated.net')) {
            return [
                'type' => 'mymemory',
                'url' => $providerUrl,
                'label' => 'mymemory-public',
            ];
        }

        return [
            'type' => 'remote',
            'url' => $providerUrl,
            'label' => 'remote',
        ];
    }

    private function translateWithRemoteProvider(
        string $text,
        string $targetLanguage,
        string $sourceLanguage,
        string $detectedLanguage,
        string $providerUrl
    ): array {
        $response = $this->httpClient->request('POST', $providerUrl, [
            'timeout' => $this->timeout,
            'json' => array_filter([
                'q' => $text,
                'source' => 'auto' === $sourceLanguage ? 'auto' : $sourceLanguage,
                'target' => $targetLanguage,
                'format' => 'text',
                'api_key' => $this->providerApiKey,
            ], static fn (mixed $value): bool => null !== $value && '' !== $value),
        ]);

        $payload = $response->toArray(false);
        $translatedText = $payload['translatedText']
            ?? $payload['data']['translations'][0]['translatedText']
            ?? $payload['responseData']['translatedText']
            ?? null;

        if (!is_string($translatedText) || '' === trim($translatedText)) {
            return [
                'success' => false,
                'message' => 'Translation provider returned an unexpected response.',
                'source_language' => $detectedLanguage,
                'target_language' => $targetLanguage,
                'provider' => 'remote',
            ];
        }

        return [
            'success' => true,
            'translated_text' => $translatedText,
            'source_language' => $payload['detectedLanguage']['language']
                ?? $payload['detected_language']
                ?? $detectedLanguage,
            'target_language' => $targetLanguage,
            'provider' => 'remote',
        ];
    }

    private function translateWithMyMemory(
        string $text,
        string $targetLanguage,
        string $sourceLanguage,
        string $detectedLanguage,
        string $providerUrl
    ): array {
        $resolvedSource = 'auto' === $sourceLanguage ? $detectedLanguage : $sourceLanguage;
        $response = $this->httpClient->request('GET', $providerUrl, [
            'timeout' => $this->timeout,
            'query' => [
                'q' => $text,
                'langpair' => sprintf('%s|%s', $resolvedSource, $targetLanguage),
            ],
        ]);

        $payload = $response->toArray(false);
        $translatedText = $payload['responseData']['translatedText'] ?? null;

        if (!is_string($translatedText) || '' === trim($translatedText)) {
            return [
                'success' => false,
                'message' => 'Public translation service returned an unexpected response.',
                'source_language' => $resolvedSource,
                'target_language' => $targetLanguage,
                'provider' => 'mymemory-public',
            ];
        }

        return [
            'success' => true,
            'translated_text' => html_entity_decode($translatedText, ENT_QUOTES | ENT_HTML5),
            'source_language' => $resolvedSource,
            'target_language' => $targetLanguage,
            'provider' => 'mymemory-public',
        ];
    }

    /**
     * @return string[]
     */
    private function splitIntoChunks(string $text): array
    {
        $paragraphs = preg_split("/\R{2,}/u", trim($text)) ?: [];
        if ([] === $paragraphs) {
            return [trim($text)];
        }

        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ('' === $paragraph) {
                continue;
            }

            if (mb_strlen($paragraph) > self::PUBLIC_CHUNK_LENGTH) {
                if ('' !== $buffer) {
                    $chunks[] = $buffer;
                    $buffer = '';
                }

                foreach ($this->splitLongParagraph($paragraph) as $part) {
                    $chunks[] = $part;
                }

                continue;
            }

            $candidate = '' === $buffer ? $paragraph : $buffer . "\n\n" . $paragraph;
            if (mb_strlen($candidate) > self::PUBLIC_CHUNK_LENGTH) {
                $chunks[] = $buffer;
                $buffer = $paragraph;
                continue;
            }

            $buffer = $candidate;
        }

        if ('' !== $buffer) {
            $chunks[] = $buffer;
        }

        return array_values(array_filter($chunks, static fn (string $chunk): bool => '' !== trim($chunk)));
    }

    /**
     * @return string[]
     */
    private function splitLongParagraph(string $paragraph): array
    {
        $sentences = preg_split('/(?<=[\.\!\?\;])\s+/u', $paragraph) ?: [$paragraph];
        $chunks = [];
        $buffer = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ('' === $sentence) {
                continue;
            }

            $candidate = '' === $buffer ? $sentence : $buffer . ' ' . $sentence;
            if (mb_strlen($candidate) > self::PUBLIC_CHUNK_LENGTH) {
                if ('' !== $buffer) {
                    $chunks[] = $buffer;
                }

                if (mb_strlen($sentence) <= self::PUBLIC_CHUNK_LENGTH) {
                    $buffer = $sentence;
                    continue;
                }

                foreach (mb_str_split($sentence, self::PUBLIC_CHUNK_LENGTH) as $part) {
                    $chunks[] = $part;
                }

                $buffer = '';
                continue;
            }

            $buffer = $candidate;
        }

        if ('' !== $buffer) {
            $chunks[] = $buffer;
        }

        return $chunks;
    }
}
