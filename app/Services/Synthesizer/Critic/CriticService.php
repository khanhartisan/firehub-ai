<?php

namespace App\Services\Synthesizer\Critic;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
use App\Contracts\Synthesizer\Critic\Critic;
use App\Contracts\Synthesizer\Critic\Criticism;
use App\Contracts\Synthesizer\Critic\Rectification;

abstract class CriticService implements Critic
{
    protected string $purpose;

    /** @var array<string, mixed> */
    protected array $config = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected CriticManager $criticManager,
        string $purpose,
        array $config = [],
    ) {
        $this->purpose = $criticManager->resolvePurpose($purpose);
        $this->config = $config;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function criticizeArticle(
        Article $article,
        ?SemanticContext $authorContext = null,
        ?SemanticContext $generalContext = null,
        array $lastRectifications = [],
    ): array {
        $rectifiedReferences = $this->collectRectifiedReferences($lastRectifications);
        $articleData = $article->toArray();

        if (($articleData['children'] ?? []) === []) {
            return [];
        }

        $allowedReferences = $this->collectAllowedReferences($articleData, $rectifiedReferences);

        if ($allowedReferences === []) {
            return [];
        }

        $payload = [
            'article' => $articleData,
            'author_context' => $authorContext?->toArray(),
            'general_context' => $generalContext?->toArray(),
            'last_rectifications' => array_values(array_map(
                static fn (Rectification $rectification): array => $rectification->toArray(),
                array_filter(
                    $lastRectifications,
                    static fn (mixed $rectification): bool => $rectification instanceof Rectification
                )
            )),
        ];

        return $this->criticize($payload, $allowedReferences, $rectifiedReferences);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $allowedReferences
     * @param  array<string, true>  $rectifiedReferences
     * @return list<Criticism>
     */
    abstract protected function criticize(
        array $payload,
        array $allowedReferences,
        array $rectifiedReferences,
    ): array;

    /**
     * @param  Rectification[]  $lastRectifications
     * @return array<string, true>
     */
    protected function collectRectifiedReferences(array $lastRectifications): array
    {
        $references = [];

        foreach ($lastRectifications as $rectification) {
            if (! $rectification instanceof Rectification) {
                continue;
            }

            $reference = trim((string) ($rectification->getReference() ?? ''));
            if ($reference !== '') {
                $references[$reference] = true;
            }
        }

        return $references;
    }

    /**
     * @param  array<string, mixed>  $articleData
     * @param  array<string, true>  $rectifiedReferences
     * @return list<string>
     */
    protected function collectAllowedReferences(array $articleData, array $rectifiedReferences): array
    {
        $references = [];
        $this->walkArticleNodes($articleData, function (string $reference) use (&$references, $rectifiedReferences): void {
            if (! isset($rectifiedReferences[$reference])) {
                $references[] = $reference;
            }
        });

        return array_values(array_unique($references));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function extractNodeText(array $node): string
    {
        $parts = [];

        foreach ($node['children'] ?? [] as $child) {
            if (is_string($child)) {
                $text = trim($child);
                if ($text !== '') {
                    $parts[] = $text;
                }

                continue;
            }

            if (is_array($child)) {
                $text = $this->extractNodeText($child);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return trim(implode(' ', $parts));
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  callable(string): void  $onReference
     */
    protected function walkArticleNodes(array $node, callable $onReference): void
    {
        $reference = trim((string) ($node['identifier'] ?? ''));
        if ($reference !== '') {
            $onReference($reference);
        }

        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $this->walkArticleNodes($child, $onReference);
            }
        }
    }
}
