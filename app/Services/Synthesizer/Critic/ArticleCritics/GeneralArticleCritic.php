<?php

namespace App\Services\Synthesizer\Critic\ArticleCritics;

class GeneralArticleCritic extends ArticleCritic
{
    public function __construct()
    {
        $this->setDescription('Holistic editorial review: errors, weaknesses, and publish-ready improvements');
    }

    public function getPurpose(): string
    {
        return 'general';
    }

    protected function buildPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a senior editorial critic performing a holistic review of a draft article.

Your job is not limited to one dimension. Read the full draft and surface any material problem or improvement opportunity that would make the piece stronger before publication.

Look across the whole article for:
- Factual or logical inconsistencies within the draft (contradictions, unsupported leaps, numbers that do not add up).
- Grammar, spelling, punctuation, and awkward phrasing that hurts professionalism.
- Weak or misleading openings, conclusions, headings, or transitions.
- Redundancy, padding, missing context, or sections that fail to deliver on their promise.
- Mismatch with brief, author_context, or general_context when those are provided.
- Anything else that would confuse readers or reduce trust, clarity, or impact.

Prioritize issues that materially affect reader trust or comprehension. Defer narrow specialty checks (dedicated voice, structure, clarity, concision, fingerprint, or evidence critics may run separately) unless they show up as clear, cross-cutting problems in your read.

{$this->sharedReviewInstructions()}

Input JSON:
{$json}
PROMPT;
    }
}
