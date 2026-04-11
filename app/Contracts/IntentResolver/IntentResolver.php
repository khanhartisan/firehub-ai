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
     * @return list<KeywordData>
     */
    public function guessKeywords(IntentData $intentData): array;

    /**
     * Receive a list of keywords and guess the possible intents for them.
     * Then return a list of IntentKeywordsData.
     * Intent may have to many keywords, a keyword may also belong to many intents.
     *
     * @param list<string> $keywords
     * @return list<IntentKeywordsData>
     */
    public function guessIntents(array $keywords): array;

    /**
     * Assign a relevance score (0–1) to each given keyword for the resolved intent.
     *
     * @param  list<string|KeywordData>  $keywords  Keywords to score (strings or {@see KeywordData}; only the keyword text is used).
     * @return list<KeywordData> One row per input keyword (after normalisation), in input order.
     */
    public function scoreKeywords(IntentData $intentData, array $keywords): array;
}
