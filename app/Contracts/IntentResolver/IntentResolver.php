<?php

namespace App\Contracts\IntentResolver;

/**
 * Infers search intent (informational, transactional, etc.) from text and suggests keywords.
 *
 * Implementations may use LLMs or heuristics. Drivers are selected via the intent resolver manager.
 */
interface IntentResolver
{
    /**
     * Analyze unstructured content and return a structured intent (title, description, intent types).
     *
     * @param  string  $content  Raw or HTML content (e.g. page body, user query context).
     */
    public function resolve(string $content): IntentData;

    /**
     * Propose search-engine-style keywords that match the given resolved intent.
     *
     * @return list<IntentKeywordData>
     */
    public function guessKeywords(IntentData $intentData): array;

    /**
     * Assign a relevance score (0–1) to each given keyword for the resolved intent.
     *
     * @param  list<string|IntentKeywordData>  $keywords  Keywords to score (strings or {@see IntentKeywordData}; only the keyword text is used).
     * @return list<IntentKeywordData> One row per input keyword (after normalisation), in input order.
     */
    public function scoreKeywords(IntentData $intentData, array $keywords): array;
}
