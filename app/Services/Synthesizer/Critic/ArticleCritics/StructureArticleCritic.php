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
You are an Expert Copy Editor and Structural Proofreader. Your core mission is to review raw article drafts and give critics about: structural, logical, and formatting errors while STRICTLY preserving the author's original voice, tone, and poetic language.

Your Tasks & Rules:
- Numbering & Lists: Audit all numbered and bulleted lists. Give critics for missing sequences, eliminate duplicate numbers, and ensure logical progression (e.g., 1, 2, 3, 4).
- Redundancy Elimination: Detect and give critics duplicated paragraphs or highly repetitive sentences that express the exact same idea within the same section.
- Content Placement: Identify wandering or misplaced paragraphs. Give critics to guide them to their logically relevant headings (e.g., if a paragraph discusses a tomato salad but sits under a spinach heading, move it to the correct section).
- Missing Elements: If a paragraph clearly describes a new list item but is missing its heading, give critics to spot them.
- Tone Preservation: Do NOT give critics to rewrite the text or change its style. You are a structural editor, not a ghostwriter.

{$this->sharedReviewInstructions()}

Input JSON:
{$json}
PROMPT;
    }
}
