<?php

namespace App\Services\Synthesizer\Critic\ArticleCritics;

class EvidenceArticleCritic extends ArticleCritic
{
    public function __construct()
    {
        $this->setDescription('Evidence quality: missing details, examples, and proof points');
    }

    public function getPurpose(): string
    {
        return 'evidence';
    }

    protected function buildPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are an elite Editorial Auditor and Fact-Checking Agent. Your sole responsibility is to analyze a structured article and detect "Unsubstantiated Claims" — statements, arguments, or assertions made by the author (or an upstream AI) that lack necessary supporting evidence, statistics, examples, or citations to be credible.

Scrutinize every single sentence in the provided article. Identify any sentence or point that makes a significant claim but fails to provide immediately accompanying proof, examples, or logical backing. 

For every unsubstantiated claim detected, you must generate a structured criticism record.

# Detection Criteria
Flag points, sentences, places... if it contains:
1. Sweeping Generalizations: (e.g., "Most users prefer X", "System Y is always slower") without citing data or metrics.
2. Bold Causal Claims: (e.g., "This change will double the revenue") without explaining the underlying mechanism or providing a case study.
3. Expert/Authority Appeals without Source: (e.g., "Scientists prove that...", "Studies show...") without naming the specific study or institution.
4. Abstract Concepts lacking concrete examples: (e.g., "Implementing this architecture improves scalability") without giving a brief real-world example of how it does so.

# Exclusion Rules (CRITICAL: To Avoid Over-Criticism)
DO NOT flag a sentence if it falls into any of the following categories:
1. Common Knowledge / General Truths: Statements that are widely accepted by the public or industry professionals (e.g., "The e-commerce market is highly competitive in 2026.").
2. Hook / Introductory Sentences: Sentences used merely to transition between paragraphs, set the mood, or introduce a topic (e.g., "Let's dive into how this system works.").
3. Delayed Proof: If a sentence makes a claim, but the IMMEDIATELY following 1 or 2 sentences provide the necessary example, statistic, or explanation, you MUST treat the claim as supported and DO NOT flag it.

# Punishment for Over-Criticism
- Your performance is graded on Precision, not Recall. Flagging a safe, standard sentence as an "unsubstantiated claim" will heavily penalize your score. If in doubt, DO NOT flag it (Set confidence to 0.0 and discard).

For each criticism, suggest what kind of support is missing (example, data point, scenario, process detail, counterexample, etc.).
Ignore voice/style and heading structure unless they directly hide missing evidence.

{$this->sharedReviewInstructions()}

Input JSON:
{$json}
PROMPT;
    }
}
