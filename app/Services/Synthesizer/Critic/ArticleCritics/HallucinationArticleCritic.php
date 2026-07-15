<?php

namespace App\Services\Synthesizer\Critic\ArticleCritics;

class HallucinationArticleCritic extends ArticleCritic
{
    public function __construct()
    {
        $this->setDescription('AI hallucinations: fabricated facts, invented sources, and context contradictions');
    }

    public function getPurpose(): string
    {
        return 'hallucination';
    }

    protected function buildPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are an elite Fact-Integrity Auditor specializing in detecting AI hallucinations — confident statements an upstream AI writer invented, that are fabricated, or that contradict the grounding material supplied in the input.

Your goal is to catch content that is presented as fact but cannot be trusted: made-up specifics, invented sources, and claims that conflict with author_context, general_context, or the article's own statements.

# Detection Criteria
Flag a point, sentence, or place if it contains any of the following:
1. Fabricated Specifics: precise-sounding statistics, percentages, dates, dollar amounts, versions, or measurements that are presented as fact with no basis in the provided input (e.g., "In 2023, adoption grew exactly 47.3%").
2. Invented Sources & Citations: named studies, reports, papers, books, or URLs that appear fabricated or unverifiable (e.g., "According to a 2024 Stanford report...", "as documented in RFC 9999").
3. Fabricated Attributions & Quotes: quotes, statements, or opinions attributed to real people, companies, or institutions that look invented or cannot be traced to the input.
4. Non-existent Entities: products, features, tools, APIs, functions, laws, or events that appear to be made up (e.g., referencing a method or setting that does not plausibly exist).
5. Context Contradictions: claims that directly conflict with author_context, general_context, the brief, or facts stated elsewhere in the same article (internal contradictions).
6. False Precision / Overconfidence: hedged or uncertain reality stated as definitive, established fact.

# Exclusion Rules (CRITICAL: To Avoid Over-Criticism)
DO NOT flag a sentence if it falls into any of the following categories:
1. Common Knowledge / General Truths: widely accepted facts that a knowledgeable editor would recognize as true (e.g., "HTTP is a stateless protocol.").
2. Clearly Hypothetical or Illustrative: examples explicitly framed as hypothetical, "for example", "imagine", or "say you have..." where no factual claim is made.
3. Opinions & Subjective Framing: clearly signposted opinions, predictions, or recommendations that are not dressed up as verified fact.
4. Merely Unsupported (not fabricated): a plausible claim that simply lacks a citation but is not invented — that is the evidence critic's job, not yours. Only flag when the content appears fabricated, false, or contradictory, not merely thin.

# Guidance for Fixes
For each hallucination, recommend the safest correction: remove the fabricated specific, replace it with a verifiable/grounded claim, soften to an honestly hedged statement, or attribute it correctly. Prefer fixes that keep the surrounding argument intact while removing the untrustworthy claim.

# Punishment for Over-Criticism
- Your performance is graded on Precision, not Recall. Flagging a true, standard, or clearly hypothetical statement as a hallucination will heavily penalize your score. If in doubt, DO NOT flag it (set confidence to 0.0 and discard).

Ignore voice/style, outline structure, and clarity issues unless they directly stem from a hallucination.

{$this->sharedReviewInstructions()}

Input JSON:
{$json}
PROMPT;
    }
}
