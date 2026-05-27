<?php

namespace App\Services\Synthesizer\Critic\Drivers;

use App\Contracts\Synthesizer\Critic\Criticism;
use App\Services\Synthesizer\Critic\CriticManager;
use App\Services\Synthesizer\Critic\CriticService;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;

class BasicCriticDriver extends CriticService
{
    protected const int MIN_SECTION_WORDS = 25;

    protected const int MIN_SECTION_CHARACTERS = 50;

    /**
     * High-signal phrases commonly associated with AI-generated prose.
     *
     * @var list<string>
     */
    protected const AI_FINGERPRINT_PHRASES = [
        "it's important to note",
        'it is important to note',
        "it's worth noting",
        'it is worth noting',
        'in conclusion',
        'in today\'s',
        'at the end of the day',
        'whether you\'re',
        'furthermore',
        'moreover',
        'additionally',
        'delve',
        'game-changer',
        'game changer',
        'dive deep',
        'unlock the',
        'navigate the',
        'comprehensive guide',
        'robust solution',
        'let\'s explore',
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(CriticManager $criticManager, string $purpose, array $config = [])
    {
        parent::__construct(
            $criticManager,
            $purpose,
            array_merge(SynthesizerSubserviceConfig::settings('critic'), $config),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $allowedReferences
     * @param  array<string, true>  $rectifiedReferences
     * @return list<Criticism>
     */
    protected function criticize(
        array $payload,
        array $allowedReferences,
        array $rectifiedReferences,
    ): array {
        return match ($this->purpose) {
            'clarity' => $this->criticizeClarity($payload, $rectifiedReferences),
            'voice' => $this->criticizeVoice($payload, $rectifiedReferences),
            'fingerprint' => $this->criticizeFingerprint($payload, $rectifiedReferences),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, true>  $rectifiedReferences
     * @return list<Criticism>
     */
    protected function criticizeClarity(array $payload, array $rectifiedReferences): array
    {
        $article = $payload['article'] ?? null;
        if (! is_array($article)) {
            return [];
        }

        $criticisms = [];

        $this->walkArticleNodes($article, function (string $reference) use (&$criticisms, $article, $rectifiedReferences): void {
            if (isset($rectifiedReferences[$reference])) {
                return;
            }

            $node = $this->findNodeByReference($article, $reference);
            if ($node !== null && ($node['type'] ?? null) === 'article') {
                return;
            }

            $text = $this->extractTextForReference($article, $reference);
            $wordCount = $this->countWords($text);

            if ($text === '') {
                $criticisms[] = (new Criticism)
                    ->setPurpose($this->purpose)
                    ->setReference($reference)
                    ->setConfidence(0.95)
                    ->setImportance(0.9)
                    ->setRemarks(['Section has no readable content.']);

                return;
            }

            if ($wordCount < self::MIN_SECTION_WORDS || strlen($text) < self::MIN_SECTION_CHARACTERS) {
                $criticisms[] = (new Criticism)
                    ->setPurpose($this->purpose)
                    ->setReference($reference)
                    ->setConfidence(0.85)
                    ->setImportance(0.75)
                    ->setRemarks([
                        sprintf(
                            'Section is too thin (%d words); expand with supporting detail or examples.',
                            $wordCount
                        ),
                    ]);
            }
        });

        return $criticisms;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, true>  $rectifiedReferences
     * @return list<Criticism>
     */
    protected function criticizeVoice(array $payload, array $rectifiedReferences): array
    {
        $article = $payload['article'] ?? null;
        if (! is_array($article)) {
            return [];
        }

        $articleText = strtolower($this->extractNodeText($article));
        if ($articleText === '') {
            return [];
        }

        $remarks = $this->findMissingContextRemarks(
            $articleText,
            $payload['author_context'] ?? null,
            $payload['general_context'] ?? null,
        );

        if ($remarks === []) {
            return [];
        }

        $articleReference = trim((string) ($article['identifier'] ?? ''));
        if ($articleReference === '' || isset($rectifiedReferences[$articleReference])) {
            return [];
        }

        return [
            (new Criticism)
                ->setPurpose($this->purpose)
                ->setReference($articleReference)
                ->setConfidence(0.7)
                ->setImportance(0.65)
                ->setRemarks($remarks),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, true>  $rectifiedReferences
     * @return list<Criticism>
     */
    protected function criticizeFingerprint(array $payload, array $rectifiedReferences): array
    {
        $article = $payload['article'] ?? null;
        if (! is_array($article)) {
            return [];
        }

        $criticisms = [];

        $this->walkArticleNodes($article, function (string $reference) use (&$criticisms, $article, $rectifiedReferences): void {
            if (isset($rectifiedReferences[$reference])) {
                return;
            }

            $node = $this->findNodeByReference($article, $reference);
            if ($node !== null && ($node['type'] ?? null) === 'article') {
                return;
            }

            $text = $this->extractTextForReference($article, $reference);
            if ($text === '') {
                return;
            }

            $matches = $this->findAiFingerprintPhrases($text);
            if ($matches === []) {
                return;
            }

            $criticisms[] = (new Criticism)
                ->setPurpose($this->purpose)
                ->setReference($reference)
                ->setConfidence(0.8)
                ->setImportance(0.7)
                ->setRemarks([
                    sprintf(
                        'Section reads like AI-generated copy (detected: %s). Rewrite with concrete detail and natural phrasing.',
                        implode(', ', $matches)
                    ),
                ]);
        });

        return $criticisms;
    }

    /**
     * @return list<string>
     */
    protected function findAiFingerprintPhrases(string $text): array
    {
        $haystack = strtolower($text);
        $matches = [];

        foreach (self::AI_FINGERPRINT_PHRASES as $phrase) {
            if (str_contains($haystack, $phrase)) {
                $matches[] = $phrase;
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * @param  array<string, mixed>  $article
     */
    protected function extractTextForReference(array $article, string $reference): string
    {
        $node = $this->findNodeByReference($article, $reference);

        return $node !== null ? $this->extractNodeText($node) : '';
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    protected function findNodeByReference(array $node, string $reference): ?array
    {
        if (trim((string) ($node['identifier'] ?? '')) === $reference) {
            return $node;
        }

        foreach ($node['children'] ?? [] as $child) {
            if (! is_array($child)) {
                continue;
            }

            $found = $this->findNodeByReference($child, $reference);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  mixed  $authorContext
     * @param  mixed  $generalContext
     * @return list<string>
     */
    protected function findMissingContextRemarks(
        string $articleText,
        mixed $authorContext,
        mixed $generalContext,
    ): array {
        $remarks = [];

        foreach ([$authorContext, $generalContext] as $context) {
            if (! is_array($context)) {
                continue;
            }

            foreach ($this->extractContextKeywords($context) as $keyword) {
                if (! str_contains($articleText, $keyword)) {
                    $remarks[] = sprintf(
                        'Article may not reflect required context keyword "%s".',
                        $keyword
                    );
                }
            }
        }

        return array_values(array_unique($remarks));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    protected function extractContextKeywords(array $context): array
    {
        $keywords = [];

        foreach ($context as $entry) {
            if (! is_array($entry) || ! isset($entry['value']) || ! is_string($entry['value'])) {
                continue;
            }

            foreach ($this->tokenize($entry['value']) as $token) {
                $keywords[] = $token;
            }
        }

        return array_values(array_unique($keywords));
    }

    protected function countWords(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $tokens = preg_split('/\s+/u', $text) ?: [];

        return count(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    /**
     * @return list<string>
     */
    protected function tokenize(string $text): array
    {
        $tokens = preg_split('/[^a-z0-9]+/i', strtolower($text)) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            static fn (string $token): bool => strlen($token) >= 5
        )));
    }
}
