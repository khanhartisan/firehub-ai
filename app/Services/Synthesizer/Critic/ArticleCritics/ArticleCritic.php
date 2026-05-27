<?php

namespace App\Services\Synthesizer\Critic\ArticleCritics;

use App\Contracts\Describable;

/**
 * A specialized critic that reviews one dimension of an article (voice, structure, clarity, fingerprint, etc.).
 *
 * Subclasses declare purpose-specific prompts; orchestrators run many critics and merge results.
 */
abstract class ArticleCritic implements Describable
{
    use \App\Concerns\Describable;

    /**
     * Stable machine key for this critic (e.g. voice, structure, clarity, fingerprint).
     */
    abstract public function getPurpose(): string;

    /**
     * Build the model prompt for this critic's specialty.
     *
     * @param  array<string, mixed>  $payload
     */
    abstract protected function buildPrompt(array $payload): string;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function renderPrompt(array $payload): string
    {
        return $this->buildPrompt($payload);
    }

    public function responseSchemaName(): string
    {
        return $this->getResponseSchemaName();
    }

    /**
     * JSON-schema response name for structured output.
     */
    protected function getResponseSchemaName(): string
    {
        return 'critic_'.str_replace('-', '_', $this->getPurpose());
    }

    /**
     * Shared instructions appended to every purpose-specific prompt.
     */
    protected function sharedReviewInstructions(): string
    {
        return <<<'TEXT'
- Return only actionable criticisms tied to a valid "reference" from the input sections.
- Set confidence (0–1): how sure you are the issue is real.
- Set importance (0–1): how strongly the author should fix it before publish.
- Omit nitpicks; return an empty criticisms array when the draft totally passes your lens.
TEXT;
    }
}
