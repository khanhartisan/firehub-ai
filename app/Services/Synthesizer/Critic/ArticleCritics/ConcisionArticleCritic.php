<?php

namespace App\Services\Synthesizer\Critic\ArticleCritics;

class ConcisionArticleCritic extends ArticleCritic
{
    public function __construct()
    {
        $this->setDescription('Redundancy, wordiness, and tighten-without-losing-meaning edits');
    }

    public function getPurpose(): string
    {
        return 'concision';
    }

    protected function buildPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are an editorial critic specializing in concision—cutting redundancy and wordiness while preserving meaning.

Review the draft only for padding and repetition:
- Redundant phrases, tautologies, and filler (e.g. "in order to", "due to the fact that", "each and every").
- Repeated ideas, sentences, or examples across sections or within the same paragraph.
- Wordy constructions that can be shortened without losing precision.
- Throat-clearing intros, stacked adverbs, and hedge piles that add length but not value.

Suggest concrete trims or rewrites. Do not flag necessary technical precision, deliberate emphasis, or brief context readers need.
Ignore voice branding, outline structure, missing evidence, and comprehension gaps unless they appear purely as redundant wording.

{$this->sharedReviewInstructions()}

Input JSON:
{$json}
PROMPT;
    }
}
