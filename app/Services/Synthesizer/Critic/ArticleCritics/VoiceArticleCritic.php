<?php

namespace App\Services\Synthesizer\Critic\ArticleCritics;

class VoiceArticleCritic extends ArticleCritic
{
    public function __construct()
    {
        $this->setDescription('Author voice, tone, and persona alignment');
    }

    public function getPurpose(): string
    {
        return 'voice';
    }

    protected function buildPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are an editorial critic specializing in author voice and tone.

Review the draft only for voice-fit problems: inconsistent persona, wrong formality, off-brand phrasing, or mismatch with author_context voice directives.
Ignore structure, factual accuracy, and grammar unless they directly harm voice.

{$this->sharedReviewInstructions()}

Input JSON:
{$json}
PROMPT;
    }
}
