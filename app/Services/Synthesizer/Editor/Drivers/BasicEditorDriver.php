<?php

namespace App\Services\Synthesizer\Editor\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Services\Synthesizer\Editor\EditorService;

class BasicEditorDriver extends EditorService
{
    public function determineAuthorContext(Idea $idea, array $authorContexts): SemanticContext
    {
        $contexts = array_values(array_filter(
            $authorContexts,
            static fn (mixed $context): bool => $context instanceof SemanticContext
        ));

        if ($contexts === []) {
            throw new \InvalidArgumentException('At least one author context is required.');
        }

        $best = $contexts[0];
        $bestScore = $this->scoreAuthorContextForIdea($idea, $best);

        foreach (array_slice($contexts, 1) as $context) {
            $score = $this->scoreAuthorContextForIdea($idea, $context);
            if ($score > $bestScore) {
                $best = $context;
                $bestScore = $score;
            }
        }

        return $best->clone();
    }

    public function distillAuthorContextForOutlineItem(
        Outline $outline,
        string $outlineItemIdentifier,
        SemanticContext $authorContext,
        ?SemanticContext $generalContext = null
    ): SemanticContext {
        $item = $this->findOutlineItem($outline, $outlineItemIdentifier);
        if (! $item instanceof OutlineItem) {
            throw new \InvalidArgumentException(sprintf(
                'Outline item "%s" was not found.',
                $outlineItemIdentifier
            ));
        }

        $distilled = $this->newContextLike($authorContext);

        foreach ($authorContext->toArray() as $key => $entry) {
            if (! is_array($entry) || ! array_key_exists('value', $entry) || ! is_string($entry['description'] ?? null)) {
                continue;
            }

            if (! $this->contextEntryHasContent($entry['value'])) {
                continue;
            }

            $distilled->set($key, $entry['description'], $entry['value']);
        }

        $point = $item->getPoint();
        $headline = trim((string) ($point->getHeadline() ?? ''));
        if ($headline !== '') {
            $distilled->set(
                'section_headline',
                'Headline for the outline section being written.',
                $headline
            );
        }

        $description = trim((string) $point->getDescription());
        if ($description !== '') {
            $distilled->set(
                'section_description',
                'Description for the outline section being written.',
                $description
            );
        }

        $guidelines = array_values(array_filter(array_map(
            static fn (mixed $line): string => trim((string) $line),
            $item->getGuidelines()
        )));
        if ($guidelines !== []) {
            $distilled->set(
                'section_guidelines',
                'Writing guidelines for the outline section being written.',
                $guidelines
            );
        }

        if ($generalContext instanceof SemanticContext) {
            foreach (['article_context', 'client_context', 'outline_focus'] as $key) {
                $entry = $generalContext->get($key);
                if ($entry === null || ! $this->contextEntryHasContent($entry['value'] ?? null)) {
                    continue;
                }

                $distilled->set(
                    'general_'.$key,
                    'General pipeline context relevant to this section.',
                    $entry['value']
                );
            }
        }

        return $distilled;
    }

    protected function scoreAuthorContextForIdea(Idea $idea, SemanticContext $context): float
    {
        $ideaTokens = $this->tokenize($this->buildIdeaSearchText($idea));
        if ($ideaTokens === []) {
            return $this->sumContextWeights($context);
        }

        $contextText = strtolower($this->serializeContextForMatching($context));
        $score = 0.0;

        foreach ($ideaTokens as $token) {
            if (str_contains($contextText, $token)) {
                $score += 1.0;
            }
        }

        return $score + ($this->sumContextWeights($context) * 0.1);
    }

    protected function buildIdeaSearchText(Idea $idea): string
    {
        $intent = $idea->getIntent();
        $parts = array_filter([
            $intent->getTitle(),
            $intent->getDescription(),
            $idea->getReason(),
            implode(' ', array_map(
                static fn (mixed $type): string => $type instanceof \BackedEnum
                    ? (string) $type->value
                    : (string) $type,
                $intent->getTypes()
            )),
        ]);

        return strtolower(implode(' ', $parts));
    }

    protected function serializeContextForMatching(SemanticContext $context): string
    {
        return trim((string) json_encode($context->toArray(), JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return list<string>
     */
    protected function tokenize(string $text): array
    {
        $tokens = preg_split('/[^a-z0-9]+/i', strtolower($text)) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            static fn (string $token): bool => strlen($token) >= 3
        )));
    }

    protected function sumContextWeights(SemanticContext $context): float
    {
        $sum = 0.0;
        foreach ($context->toArray() as $entry) {
            if (! is_array($entry) || ! isset($entry['weight']) || ! is_numeric($entry['weight'])) {
                continue;
            }

            $sum += (float) $entry['weight'];
        }

        return $sum;
    }

    protected function findOutlineItem(Outline $outline, string $identifier): ?OutlineItem
    {
        foreach ($outline->getItems() as $item) {
            $match = $this->findOutlineItemRecursive($item, $identifier);
            if ($match instanceof OutlineItem) {
                return $match;
            }
        }

        return null;
    }

    protected function findOutlineItemRecursive(OutlineItem $item, string $identifier): ?OutlineItem
    {
        if ($item->getIdentifier() === $identifier) {
            return $item;
        }

        foreach ($item->getSubItems() as $subItem) {
            $match = $this->findOutlineItemRecursive($subItem, $identifier);
            if ($match instanceof OutlineItem) {
                return $match;
            }
        }

        return null;
    }

    protected function newContextLike(SemanticContext $context): SemanticContext
    {
        if ($context instanceof AuthorContext) {
            return new AuthorContext;
        }

        return new SemanticContext;
    }

    protected function contextEntryHasContent(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_int($value) || is_float($value)) {
            return true;
        }

        if (! is_array($value)) {
            return true;
        }

        if ($value === []) {
            return false;
        }

        foreach ($value as $nested) {
            if ($this->contextEntryHasContent($nested)) {
                return true;
            }
        }

        return false;
    }
}
