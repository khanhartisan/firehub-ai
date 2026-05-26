<?php

namespace App\Services\Synthesizer\Critic\ArticleCritics;

class ClarityArticleCritic extends ArticleCritic
{
    public function __construct()
    {
        $this->setDescription('Clarity, readability, and reader comprehension');
    }

    public function getPurpose(): string
    {
        return 'clarity';
    }

    protected function buildPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are an editorial critic specializing in clarity and readability.

Review the draft only for comprehension problems: ambiguous sentences, undefined jargon, dense paragraphs, weak topic sentences, or missing examples where readers would be confused.
Ignore author voice preferences and high-level outline issues unless they block understanding.

{$this->sharedReviewInstructions()}

Input JSON:
{$json}
PROMPT;
    }
}
