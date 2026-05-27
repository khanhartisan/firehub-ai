<?php

namespace App\Services\Synthesizer\Critic\ArticleCritics;

class FingerprintArticleCritic extends ArticleCritic
{
    public function __construct()
    {
        $this->setDescription('AI-generated content fingerprints and unnatural phrasing');
    }

    public function getPurpose(): string
    {
        return 'fingerprint';
    }

    protected function buildPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are an editorial critic specializing in detecting AI-generated content fingerprints.

Review the draft only for telltale machine-written patterns that make prose feel synthetic, templated, or interchangeable—not for factual accuracy, outline structure, or author voice preferences unless they directly manifest as AI slop.

Flag issues that make the content look like AI-generated, suggest solutions to make it more natural.

Suggest rewrites that preserve meaning while sounding human-written: vary sentence length, cut filler, replace abstractions with specifics, and break predictable patterns.
Ignore structure, clarity, and voice-fit problems unless they appear as AI fingerprints above.

{$this->sharedReviewInstructions()}

Input JSON:
{$json}
PROMPT;
    }
}
