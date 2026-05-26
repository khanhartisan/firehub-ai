<?php

namespace App\Services\Synthesizer\Critic\ArticleCritics;

class StructureArticleCritic extends ArticleCritic
{
    public function __construct()
    {
        $this->setDescription('Article structure, flow, and section organization');
    }

    public function getPurpose(): string
    {
        return 'structure';
    }

    protected function buildPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are an editorial critic specializing in article structure and narrative flow.

Review the draft only for structural problems: weak openings, missing transitions, redundant sections, poor heading hierarchy, unbalanced section length, or logical ordering issues.
Ignore voice quirks and line-level grammar unless they break structural clarity.

{$this->sharedReviewInstructions()}

Input JSON:
{$json}
PROMPT;
    }
}
