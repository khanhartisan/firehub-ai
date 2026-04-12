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
     * Analyze unstructured content and return the resolved {@see Intent} plus {@see IntentableIntent} rows
     * (typically one row linking the input {@see Intentable} to that intent).
     *
     * @param  Intentable  $intentable  Wraps raw or HTML content (e.g. page body, user query context).
     */
    public function resolve(Intentable $intentable): IntentableIntents;

    /**
     * Return null if two intents are distinguished and should not or cannot be merged.
     * Return a new intent (merged from the 2) if they're the same and can be merged.
     *
     * @param Intent $intent1
     * @param Intent $intent2
     * @return Intent|null
     */
    public function mergeIntents(Intent $intent1, Intent $intent2): ?Intent;

    /**
     * Propose search-engine-style keywords that match the given resolved intent.
     *
     * @return list<IntentKeyword>
     */
    public function guessIntentKeywords(Intent $intentData): array;

    /**
     * Receive a list of keywords and guess the possible intents for them.
     * Then return a list of IntentKeywords.
     * Intent may have to many keywords, a keyword may also belong to many intents.
     *
     * @param  list<string>  $keywords
     * @return list<IntentKeywords>
     */
    public function inferFromKeywords(array $keywords): array;

    /**
     * Assign a relevance score (0–1) to each given keyword for the resolved intent.
     *
     * @param  list<string|IntentKeyword>  $keywords  Keywords to score (strings or {@see IntentKeyword}; only the keyword text is used).
     * @return list<IntentKeyword> One row per input keyword (after normalisation), in input order.
     */
    public function scoreKeywords(Intent $intentData, array $keywords): array;
}
