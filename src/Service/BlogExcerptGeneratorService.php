<?php

namespace App\Service;

class BlogExcerptGeneratorService
{
    public function generate(?string $title, ?string $content, int $maxLength = 180): string
    {
        $title = trim((string) $title);
        $content = trim(strip_tags((string) $content));

        if ('' === $content) {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', $content) ?? $content;
        $sentences = preg_split('/(?<=[\.\!\?\;])\s+/u', $normalized) ?: [$normalized];
        $sentences = array_values(array_filter(array_map('trim', $sentences)));

        if ([] === $sentences) {
            return $this->truncate($normalized, $maxLength);
        }

        $keywords = $this->extractKeywords($title . ' ' . $normalized);
        $bestSentence = $sentences[0];
        $bestScore = -1;

        foreach ($sentences as $index => $sentence) {
            $score = $this->scoreSentence($sentence, $keywords, $index);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSentence = $sentence;
            }
        }

        $location = $this->extractLocation($title, $sentences);
        $theme = $this->extractTheme($keywords, $normalized);
        $hook = $this->buildHook($location, $theme);
        $detail = $this->normalizeSentence($bestSentence);

        $excerpt = trim($hook . ' ' . $this->shortenForDetail($detail, 110));

        if (mb_strlen($excerpt) < (int) floor($maxLength * 0.6) && isset($sentences[1])) {
            $excerpt .= ' ' . $this->shortenForDetail($this->normalizeSentence($sentences[1]), 55);
        }

        return $this->truncate($this->cleanExcerpt($excerpt), $maxLength);
    }

    /**
     * @return string[]
     */
    private function extractKeywords(string $text): array
    {
        $text = mb_strtolower($text);
        preg_match_all('/\p{L}{4,}/u', $text, $matches);
        $words = $matches[0] ?? [];
        $stopWords = ['avec', 'dans', 'pour', 'that', 'this', 'from', 'into', 'your', 'vous', 'nous', 'their', 'mais', 'plus', 'very', 'will', 'have', 'vous', 'etre', 'sont'];
        $words = array_values(array_filter($words, static fn (string $word): bool => !in_array($word, $stopWords, true)));
        $frequencies = array_count_values($words);
        arsort($frequencies);

        return array_slice(array_keys($frequencies), 0, 12);
    }

    /**
     * @param string[] $keywords
     */
    private function scoreSentence(string $sentence, array $keywords, int $index): int
    {
        $lower = mb_strtolower($sentence);
        $score = max(0, 40 - ($index * 4));

        foreach ($keywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                $score += 8;
            }
        }

        $length = mb_strlen($sentence);
        if ($length >= 80 && $length <= 220) {
            $score += 12;
        }

        return $score;
    }

    private function truncate(string $text, int $maxLength): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLength - 3)) . '...';
    }

    /**
     * @param string[] $sentences
     */
    private function extractLocation(string $title, array $sentences): ?string
    {
        $source = trim($title . ' ' . implode(' ', array_slice($sentences, 0, 2)));
        preg_match_all('/\b[\p{Lu}][\p{L}\-]+(?:\s+[\p{Lu}][\p{L}\-]+){0,2}\b/u', $source, $matches);
        $candidates = array_values(array_filter($matches[0] ?? [], static fn (string $match): bool => mb_strlen($match) > 2));

        return $candidates[0] ?? null;
    }

    /**
     * @param string[] $keywords
     */
    private function extractTheme(array $keywords, string $content): string
    {
        $lower = mb_strtolower($content);
        $themes = [
            'plages et detente' => ['plage', 'mer', 'ile', 'cote', 'sunset'],
            'culture et patrimoine' => ['temple', 'musee', 'histoire', 'medina', 'patrimoine'],
            'road trip et aventure' => ['route', 'aventure', 'vallee', 'montagne', 'randonnee'],
            'saveurs locales' => ['cafe', 'restaurant', 'sushi', 'bagel', 'marche', 'the'],
            'escapade urbaine' => ['ville', 'quartier', 'avenue', 'bridge', 'parc', 'ruelle'],
        ];

        foreach ($themes as $theme => $markers) {
            foreach ($markers as $marker) {
                if (str_contains($lower, $marker)) {
                    return $theme;
                }
            }
        }

        return [] !== $keywords ? implode(', ', array_slice($keywords, 0, 3)) : 'voyage';
    }

    private function buildHook(?string $location, string $theme): string
    {
        if ($location) {
            return sprintf('%s se decouvre ici a travers une experience centree sur %s.', $location, $theme);
        }

        return sprintf('Ce blog met en avant une experience de voyage axee sur %s.', $theme);
    }

    private function shortenForDetail(string $text, int $maxLength): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLength - 3)) . '...';
    }

    private function normalizeSentence(string $sentence): string
    {
        $sentence = trim($sentence);
        $sentence = preg_replace('/\s+/u', ' ', $sentence) ?? $sentence;

        return ucfirst(rtrim($sentence, " \t\n\r\0\x0B."));
    }

    private function cleanExcerpt(string $excerpt): string
    {
        $excerpt = preg_replace('/\s+/u', ' ', trim($excerpt)) ?? trim($excerpt);
        $excerpt = preg_replace('/\.\s*\./u', '.', $excerpt) ?? $excerpt;

        return rtrim($excerpt, '. ') . '.';
    }
}
