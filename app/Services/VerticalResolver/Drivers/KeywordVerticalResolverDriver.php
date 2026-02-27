<?php

namespace App\Services\VerticalResolver\Drivers;

use App\Contracts\VerticalResolver\Vertical;
use App\Contracts\VerticalResolver\VerticalMatch;
use App\Contracts\VerticalResolver\VerticalResolver;
use App\Utils\HtmlCleaner;

class KeywordVerticalResolverDriver implements VerticalResolver
{
    public function __construct(
        protected array $config = []
    ) {
        $this->config = array_merge([
            'match_threshold' => 0.4,
            'proposal_threshold' => 0.15,
            'max_content_length' => 50000,
        ], $config);
    }

    /**
     * @param  Vertical[]  $verticals
     * @return VerticalMatch[]
     */
    public function resolve(string $content, array $verticals): array
    {
        if ($verticals === []) {
            return [];
        }

        $normalizedContent = $this->normalizeContent($content);
        $contentWords = $this->tokenize($normalizedContent);
        $matchThreshold = (float) ($this->config['match_threshold'] ?? 0.4);

        $scores = [];
        foreach ($verticals as $vertical) {
            $confidence = $this->scoreVertical($vertical, $normalizedContent, $contentWords);
            if ($confidence >= $matchThreshold) {
                $identifier = $vertical->getIdentifier() ?? $vertical->getName();
                $scores[] = new VerticalMatch($identifier, round($confidence, 4));
            }
        }

        usort($scores, fn (VerticalMatch $a, VerticalMatch $b) => $b->getConfidence() <=> $a->getConfidence());

        return $scores;
    }

    /**
     * @param  Vertical[]  $verticals
     * @return Vertical[]
     */
    public function propose(string $content, array $verticals): array
    {
        return [];
    }

    protected function normalizeContent(string $content): string
    {
        $maxLength = (int) ($this->config['max_content_length'] ?? 50000);

        if (strip_tags($content) !== $content) {
            $content = HtmlCleaner::clean($content, $maxLength);
            $content = strip_tags($content);
        }

        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        if (strlen($content) > $maxLength) {
            $content = substr($content, 0, $maxLength);
        }

        return mb_strtolower($content, 'UTF-8');
    }

    /**
     * @return array<int, string>
     */
    protected function tokenize(string $text): array
    {
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(array_map(
            fn (string $w) => preg_replace('/[^\p{L}\p{N}_-]/u', '', $w),
            $words
        ), fn (string $w) => strlen($w) > 1));
    }

    /**
     * @param  array<int, string>  $contentWords
     */
    protected function scoreVertical(Vertical $vertical, string $normalizedContent, array $contentWords): float
    {
        $name = mb_strtolower(trim($vertical->getName()), 'UTF-8');
        $description = $vertical->getDescription()
            ? mb_strtolower(trim($vertical->getDescription()), 'UTF-8')
            : '';

        $verticalText = $description !== '' ? $name . ' ' . $description : $name;
        $verticalWords = array_unique($this->tokenize($verticalText));

        if ($verticalWords === []) {
            return 0.0;
        }

        $contentWordSet = array_flip($contentWords);

        $matchCount = 0;
        foreach ($verticalWords as $word) {
            if (isset($contentWordSet[$word])) {
                $matchCount++;
            }
        }

        $termScore = $matchCount / count($verticalWords);

        if ($name !== '' && str_contains($normalizedContent, $name)) {
            $termScore = min(1.0, $termScore + 0.3);
        }

        return $termScore;
    }
}
