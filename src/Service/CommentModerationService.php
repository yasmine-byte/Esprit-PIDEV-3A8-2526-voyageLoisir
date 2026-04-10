<?php

namespace App\Service;

class CommentModerationService
{
    /**
     * @param string[] $blockedTerms
     */
    public function __construct(
        private readonly array $blockedTerms = [],
    ) {
    }

    public function moderate(string $content): array
    {
        $content = trim($content);
        if ('' === $content) {
            return [
                'original' => '',
                'sanitized' => '',
                'contains_blocked_words' => false,
                'matched_terms' => [],
                'match_count' => 0,
            ];
        }

        $matchedTerms = [];
        $sanitized = $content;

        foreach ($this->blockedTerms as $term) {
            $term = trim((string) $term);
            if ('' === $term) {
                continue;
            }

            $pattern = $this->buildWordPattern($term);
            $hasMatch = 1 === preg_match($pattern, $sanitized);

            if (!$hasMatch) {
                continue;
            }

            $matchedTerms[] = $term;
            $sanitized = (string) preg_replace_callback(
                $pattern,
                static fn (array $matches): string => self::maskWord($matches[0]),
                $sanitized
            );
        }

        $matchedTerms = array_values(array_unique($matchedTerms));

        return [
            'original' => $content,
            'sanitized' => $sanitized,
            'contains_blocked_words' => [] !== $matchedTerms,
            'matched_terms' => $matchedTerms,
            'match_count' => count($matchedTerms),
        ];
    }

    private function buildWordPattern(string $term): string
    {
        return '/(?<!\p{L})' . preg_quote($term, '/') . '(?!\p{L})/iu';
    }

    private static function maskWord(string $word): string
    {
        $length = mb_strlen($word);
        if ($length <= 2) {
            return str_repeat('*', max(1, $length));
        }

        return mb_substr($word, 0, 1)
            . str_repeat('*', max(1, $length - 2))
            . mb_substr($word, -1);
    }
}
