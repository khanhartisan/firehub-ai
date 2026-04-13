<?php

namespace App\Services\IntentResolver\Drivers;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\IntentResolver\Intentable;
use App\Contracts\IntentResolver\IntentableIntent;
use App\Contracts\IntentResolver\IntentableIntents;
use App\Contracts\IntentResolver\IntentKeyword;
use App\Contracts\IntentResolver\IntentKeywords;
use App\Contracts\IntentResolver\IntentResolver;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Services\IntentResolver\IntentResolverService;
use RuntimeException;

class OpenAIIntentResolverDriver extends IntentResolverService implements IntentResolver
{
    protected OpenAIClient $openAIClient;

    protected string $defaultModel;

    public function __construct(OpenAIClient $openAIClient, array $config = [])
    {
        parent::__construct($config);

        $this->openAIClient = $openAIClient;
        $this->defaultModel = $config['model'] ?? 'gpt-4o-mini';
    }

    public function resolve(Intentable $intentable): IntentableIntents
    {
        $content = $this->prepareContent($intentable->getContent() ?? '');

        $prompt = $this->buildResolvePrompt($content);
        $jsonSchema = $this->buildResolveIntentableIntentsJsonSchema();

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->defaultModel)
            ->temperature(0)
            ->responseFormat([
                'type' => 'json_schema',
                'name' => 'resolve_intentable_intents',
                'schema' => $jsonSchema,
                'strict' => true,
            ]);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to resolve intent with OpenAI: {$e->getMessage()}",
                0,
                $e
            );
        }

        $this->checkForRefusal($response);

        $responseText = $response->getFirstOutputText();

        if ($responseText === null || $responseText === '') {
            throw new RuntimeException(
                'OpenAI returned empty intent resolution response'
            );
        }

        return $this->parseIntentableIntentsResponse($responseText, $intentable);
    }

    public function mergeIntents(Intent $intent1, Intent $intent2): ?Intent
    {
        $prompt = $this->buildMergeIntentsPrompt($intent1, $intent2);
        $jsonSchema = $this->buildMergeIntentsJsonSchema();

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->defaultModel)
            ->temperature(0)
            ->responseFormat([
                'type' => 'json_schema',
                'name' => 'merge_intents',
                'schema' => $jsonSchema,
                'strict' => true,
            ]);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to merge intents with OpenAI: {$e->getMessage()}",
                0,
                $e
            );
        }

        $this->checkForRefusal($response);

        $responseText = $response->getFirstOutputText();

        if ($responseText === null || $responseText === '') {
            throw new RuntimeException(
                'OpenAI returned empty merge-intents response'
            );
        }

        return $this->parseMergeIntentsResponse($responseText);
    }

    /**
     * @return list<IntentKeyword>
     */
    public function guessIntentKeywords(Intent $intentData): array
    {
        $prompt = $this->buildKeywordsPrompt($intentData);
        $jsonSchema = $this->buildKeywordsJsonSchema();

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->defaultModel)
            ->responseFormat([
                'type' => 'json_schema',
                'name' => 'intent_keywords',
                'schema' => $jsonSchema,
                'strict' => true,
            ]);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to guess keywords with OpenAI: {$e->getMessage()}",
                0,
                $e
            );
        }

        $this->checkForRefusal($response);

        $responseText = $response->getFirstOutputText();

        if ($responseText === null || $responseText === '') {
            throw new RuntimeException(
                'OpenAI returned empty keyword suggestion response'
            );
        }

        return $this->parseKeywordsResponse($responseText);
    }

    /**
     * @param  list<string>  $keywords
     * @return list<IntentKeywords>
     */
    public function inferFromKeywords(array $keywords): array
    {
        $normalized = $this->normalizeGuessKeywordInput($keywords);

        if ($normalized === []) {
            return [];
        }

        $maxKeywords = max(1, min(50, (int) ($this->config['max_keywords'] ?? 25)));
        if (count($normalized) > $maxKeywords) {
            throw new \InvalidArgumentException(
                sprintf('Cannot process more than %d keywords at once.', $maxKeywords)
            );
        }

        $prompt = $this->buildInferFromKeywordsPrompt($normalized);
        $jsonSchema = $this->buildInferFromKeywordsJsonSchema(count($normalized));

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->defaultModel)
            ->temperature(0)
            ->responseFormat([
                'type' => 'json_schema',
                'name' => 'infer_from_keywords',
                'schema' => $jsonSchema,
                'strict' => true,
            ]);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to guess intents from keywords with OpenAI: {$e->getMessage()}",
                0,
                $e
            );
        }

        $this->checkForRefusal($response);

        $responseText = $response->getFirstOutputText();

        if ($responseText === null || $responseText === '') {
            throw new RuntimeException(
                'OpenAI returned empty guess-intents response'
            );
        }

        return $this->parseInforFromKeywordsResponse($responseText);
    }

    /**
     * @param  list<string>  $keywords
     * @return list<string>
     */
    protected function normalizeGuessKeywordInput(array $keywords): array
    {
        $out = [];
        $seen = [];

        foreach ($keywords as $k) {
            if (! is_string($k)) {
                continue;
            }
            $s = trim($k);
            if ($s === '') {
                continue;
            }
            $key = mb_strtolower($s);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $s;
        }

        return $out;
    }

    /**
     * @param  list<string>  $keywordStrings
     */
    protected function buildMergeIntentsPrompt(Intent $intent1, Intent $intent2): string
    {
        $a = $intent1->toJson();
        $b = $intent2->toJson();

        return <<<PROMPT
You compare two resolved search intents (JSON below). Decide whether they describe the same underlying user goal and can be merged into a single intent.

If they target different goals, audiences, or are incompatible (for example conflicting commercial vs purely informational purpose), set "should_merge" to false and "merged_intent" to null.

If they are duplicates or near-duplicates that should be consolidated, set "should_merge" to true and provide "merged_intent": one intent with a clear title and description, union of applicable intent types when reasonable, and a single primary language when you can infer it.

Follow the same neutrality rules as other intent tasks: no brand or website names in title or description.
When you provide "merged_intent", the "title" and "description" must be written in the exact same language as "language" for that merged intent.

Intent A:
{$a}

Intent B:
{$b}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildMergeIntentsJsonSchema(): array
    {
        $intentObject = $this->intentDataJsonSchemaObject();

        return [
            'type' => 'object',
            'properties' => [
                'should_merge' => [
                    'type' => 'boolean',
                    'description' => 'True if both intents describe the same user goal and can be merged into one',
                ],
                'merged_intent' => [
                    'anyOf' => [
                        ['type' => 'null'],
                        $intentObject,
                    ],
                ],
            ],
            'required' => ['should_merge', 'merged_intent'],
            'additionalProperties' => false,
        ];
    }

    protected function parseMergeIntentsResponse(string $responseText): ?Intent
    {
        $data = json_decode($responseText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Failed to parse merge-intents response as JSON: '.json_last_error_msg()
            );
        }

        if (! is_array($data)) {
            throw new RuntimeException('Merge-intents response JSON did not decode to an array');
        }

        if (empty($data['should_merge'])) {
            return null;
        }

        $merged = $data['merged_intent'] ?? null;

        if (! is_array($merged)) {
            return null;
        }

        try {
            return Intent::fromArray($merged);
        } catch (\InvalidArgumentException $e) {
            throw new RuntimeException(
                'Invalid merged intent payload: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * @param  list<string>  $keywordStrings
     */
    protected function buildInferFromKeywordsPrompt(array $keywordStrings): string
    {
        $numbered = [];
        foreach ($keywordStrings as $i => $kw) {
            $numbered[] = ($i + 1).'. '.$kw;
        }
        $list = implode("\n", $numbered);

        return <<<PROMPT
You are a Senior SEO Content Architect and User Intent Analyst.

You receive a list of search keywords. Group them into one or more distinct user search intents.

# Each group must contain:
- "intent": a full intent payload (title, description, language, temporal, types) as described in the schema. Follow the same tone and neutrality rules as for page-based intent analysis: no specific brand or website names in title/description.
- "keywords": a non-empty subset of the input keywords with a relevance score (0–1) for how well each keyword fits that intent.

Language consistency rule:
- For each "intent", write both "title" and "description" in the same language as that intent's "language" field.

---

# STRICT MAPPING RULES:

## Granularity Parity: The scope of the "intent" must strictly match the scope of the "keywords".

- If a keyword is specific to a city (e.g., "New York"), the intent description must reflect that city-level scope, not a global or regional one.

- If a keyword is generic, the intent should be generic. Do not bridge a specific keyword to a broad intent.

## Constraint Validation:

- If the intent description implies a specific quality (e.g., "unique", "affordable", "expert"), the keywords assigned to it must possess clear semantic signals of that quality.

## Relevance Scoring (Strict Scale):

- 0.9 - 1.0: The keyword is an exact semantic match for the intent's scope and constraints.

- 0.6 - 0.8: The keyword fits the topic but is slightly broader or narrower than the intent.

- Below 0.5: Assign this score if there is a Scope Mismatch (e.g., assigning a specific city keyword to a general "Regional Attractions" intent).

---

A keyword may appear in more than one group if it genuinely fits multiple intents. Prefer covering every input keyword at least once across all groups (or assign it to the closest intent).

---

Input keywords:
{$list}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildInferFromKeywordsJsonSchema(int $keywordCount): array
    {
        $maxGroups = min(15, max(1, $keywordCount));

        $intentObject = $this->intentDataJsonSchemaObject();
        $keywordItem = $this->keywordDataJsonSchemaItem();

        return [
            'type' => 'object',
            'properties' => [
                'groups' => [
                    'type' => 'array',
                    'description' => 'Intent clusters; each links an inferred intent to keywords from the input',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'intent' => $intentObject,
                            'keywords' => [
                                'type' => 'array',
                                'items' => $keywordItem,
                                'minItems' => 1,
                                'maxItems' => max(1, $keywordCount),
                            ],
                        ],
                        'required' => ['intent', 'keywords'],
                        'additionalProperties' => false,
                    ],
                    'minItems' => 1,
                    'maxItems' => $maxGroups,
                ],
            ],
            'required' => ['groups'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return list<IntentKeywords>
     */
    protected function parseInforFromKeywordsResponse(string $responseText): array
    {
        $data = json_decode($responseText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Failed to parse guess-intents response as JSON: '.json_last_error_msg()
            );
        }

        if (! is_array($data)) {
            throw new RuntimeException('Guess-intents response JSON did not decode to an array');
        }

        $groups = $data['groups'] ?? [];

        if (! is_array($groups)) {
            throw new RuntimeException('Guess-intents response must contain a "groups" array');
        }

        $out = [];
        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            try {
                $out[] = IntentKeywords::fromArray($group);
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return $out;
    }

    /**
     * @param  list<string|IntentKeyword>  $keywords
     * @return list<IntentKeyword>
     */
    public function scoreKeywords(Intent $intentData, array $keywords): array
    {
        $normalized = $this->normalizeKeywordsForScoring($keywords);

        if ($normalized === []) {
            return [];
        }

        $maxKeywords = max(1, min(50, (int) ($this->config['max_keywords'] ?? 25)));
        if (count($normalized) > $maxKeywords) {
            throw new \InvalidArgumentException(
                sprintf('Cannot score more than %d keywords at once.', $maxKeywords)
            );
        }

        $prompt = $this->buildScoreKeywordsPrompt($intentData, $normalized);
        $jsonSchema = $this->buildScoreKeywordsJsonSchema(count($normalized));

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->defaultModel)
            ->responseFormat([
                'type' => 'json_schema',
                'name' => 'score_intent_keywords',
                'schema' => $jsonSchema,
                'strict' => true,
            ]);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to score keywords with OpenAI: {$e->getMessage()}",
                0,
                $e
            );
        }

        $this->checkForRefusal($response);

        $responseText = $response->getFirstOutputText();

        if ($responseText === null || $responseText === '') {
            throw new RuntimeException(
                'OpenAI returned empty keyword scoring response'
            );
        }

        return $this->parseScoreKeywordsResponse($responseText, $normalized);
    }

    /**
     * @param  list<string|IntentKeyword>  $keywords
     * @return list<string>
     */
    protected function normalizeKeywordsForScoring(array $keywords): array
    {
        $out = [];
        $seen = [];

        foreach ($keywords as $k) {
            if ($k instanceof IntentKeyword) {
                $s = $k->getKeyword();
            } elseif (is_string($k)) {
                $s = trim($k);
            } else {
                continue;
            }

            if ($s === '') {
                continue;
            }

            $key = mb_strtolower($s);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $s;
        }

        return $out;
    }

    /**
     * @param  list<string>  $keywordStrings
     */
    protected function buildScoreKeywordsPrompt(Intent $intentData, array $keywordStrings): string
    {
        $payload = $intentData->toJson();
        $numbered = [];
        foreach ($keywordStrings as $i => $kw) {
            $numbered[] = ($i + 1).'. '.$kw;
        }
        $list = implode("\n", $numbered);

        return <<<PROMPT
You score how well each search query matches the given resolved intent. Return exactly one object per keyword below, in the same order (1, 2, 3, …).

For each item, set "relevance" to a float number (precision 2) between 0 and 1, with 0 is absolutely no relevant and 1 is extract relevant, or null only if scoring is impossible.

Resolved intent (JSON):
{$payload}

Keywords to score (in order):
{$list}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildScoreKeywordsJsonSchema(int $count): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'keywords' => [
                    'type' => 'array',
                    'description' => 'One relevance score per input keyword, same order as the prompt',
                    'items' => $this->keywordDataJsonSchemaItem(),
                    'minItems' => $count,
                    'maxItems' => $count,
                ],
            ],
            'required' => ['keywords'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  list<string>  $expectedOrder
     * @return list<IntentKeyword>
     */
    protected function parseScoreKeywordsResponse(string $responseText, array $expectedOrder): array
    {
        $parsed = $this->parseKeywordsResponse($responseText);

        $byLower = [];
        foreach ($parsed as $row) {
            $byLower[mb_strtolower($row->getKeyword())] = $row;
        }

        $out = [];
        foreach ($expectedOrder as $kw) {
            $match = $byLower[mb_strtolower($kw)] ?? null;
            if ($match !== null) {
                $out[] = $match;

                continue;
            }

            $out[] = (new IntentKeyword)
                ->setKeyword($kw)
                ->setRelevance(null);
        }

        return $out;
    }

    protected function buildResolvePrompt(string $content): string
    {
        $typeLines = [];
        foreach (IntentType::cases() as $type) {
            $typeLines[] = sprintf('- %d: %s', $type->value, IntentType::describe($type));
        }
        $typeGuide = implode("\n", $typeLines);

        return <<<PROMPT
You are a Senior SEO Content Architect and User Intent Analyst.

You analyze text and infer the user's search intent.

Return one or more distinct intents in "intentable_intents". Each row is a full intent payload (title, description, language, temporal, types) plus a "relevance" score (0–1) for how strongly that intent applies to this content, or null if you cannot score it. Order rows from strongest relevance to weakest when possible.

If the content clearly satisfies only one search intent, return a single row. When the content genuinely fits multiple distinct user goals (e.g. informational plus commercial), include multiple rows with appropriate relevances.

Classify each intent using one or more intent types (use the numeric codes below). You may assign short human-readable title and description fields summarizing each intent.

Only return different intentable_intents if the intents are clearly different and should be separated. If the intents are relevant and can be merged, you should return only one intentable_intent that represent the relevant intents.

General guidelines:
- Source Neutrality: The Intent Title and Description must be Source-Agnostic.
- Banned Content: Strictly DO NOT include specific brand names, websites, authors, or organization names (e.g., "BBC", "CNN", "Wikipedia", "Caryn James") in the Title or Description.

Guidelines for the Title:
- Temporal Inclusion: If the content refers to a specific year, season, or era, the title MUST include it (e.g., "2026 Best Tools..." instead of just "Best Tools...").
- Prioritize Uniqueness: The title must be specific enough to distinguish it from similar topics in different years, regions, or levels of expertise.
- Objective: Capture the "Essence" of the content's purpose.
- Exclusion: Do not use the title of the article itself. Do not use generic words like "Content" or "Article".

Guidelines for the Description:
- Do not preamble with "This content...", "The content...",... or anything else similar.
- No preamble like "This content serves", or "This content provides...", go straight ahead.
- Perspective: Focus on why the user is reading this and what value the content provides to their decision-making process.
- Identify the target audience's goal.
- Tone: Use professional, analytical, and industry-standard terminology (e.g., "high-quality evaluation," "synthesizing critical insights," "navigating selection").

Intent type codes:
{$typeGuide}

Rules:
- "language": primary language of the content as a BCP 47 tag from the schema enum, or null if you cannot determine it reliably.
- "title" and "description" must be written in the same language as "language" for that intent.
- "temporal": temporal nature of the intent as one of the enum values in schema, or null if no temporal framing is implied.
- "types" may include multiple values when the content clearly fits more than one intent.
- Use "unknown" (6) only when the intent cannot be determined.
- Prefer specific intents over UNKNOWN when possible.

Content:
{$content}
PROMPT;
    }

    /**
     * JSON Schema for {@see IntentableIntents}: multiple intents with per-row relevance.
     *
     * @return array<string, mixed>
     */
    protected function buildResolveIntentableIntentsJsonSchema(): array
    {
        $max = max(1, min(10, (int) ($this->config['max_resolve_intents'] ?? 8)));
        $intentObject = $this->intentDataJsonSchemaObject();

        return [
            'type' => 'object',
            'properties' => [
                'intentable_intents' => [
                    'type' => 'array',
                    'description' => 'Distinct intents this content may satisfy; each with relevance for this content',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'intent' => $intentObject,
                            'relevance' => [
                                'type' => ['number', 'null'],
                                'description' => 'How strongly this content matches this intent (0–1), or null if unscored',
                            ],
                        ],
                        'required' => ['intent', 'relevance'],
                        'additionalProperties' => false,
                    ],
                    'minItems' => 1,
                    'maxItems' => $max,
                ],
            ],
            'required' => ['intentable_intents'],
            'additionalProperties' => false,
        ];
    }

    /**
     * JSON Schema for a single {@see Intent} object (nested in resolve and infer-from-keywords).
     *
     * @return array<string, mixed>
     */
    protected function intentDataJsonSchemaObject(): array
    {
        $intentValues = array_map(
            static fn (IntentType $type): int => $type->value,
            IntentType::cases()
        );

        $languageEnum = array_merge(
            [null],
            array_map(
                static fn (Language $language): string => $language->value,
                Language::cases()
            )
        );

        $temporalEnum = array_merge(
            [null],
            array_map(
                static fn (Temporal $temporal): string => $temporal->value,
                Temporal::cases()
            )
        );

        return [
            'type' => 'object',
            'properties' => $properties = [
                'title' => [
                    'type' => ['string', 'null'],
                    'description' => 'Short label for this intent in the corresponding language',
                ],
                'description' => [
                    'type' => ['string', 'null'],
                    'description' => 'Longer explanation of the inferred intent in the corresponding language. Return only the text of the description. No preamble, no explanations. DO NOT starts with preamble like "The content..." or "This content..." or any preamble similar to that. Just return straight ahead.',
                    'minLength' => 100,
                    'maxLength' => 500,
                ],
                'language' => [
                    'type' => ['string', 'null'],
                    'description' => 'Primary language of the content (BCP 47 tag)',
                    'enum' => $languageEnum,
                ],
                'temporal' => [
                    'type' => ['string', 'null'],
                    'description' => 'Temporal nature of the intent',
                    'enum' => $temporalEnum,
                ],
                'types' => [
                    'type' => 'array',
                    'description' => 'Search intent classification codes',
                    'items' => [
                        'type' => 'integer',
                        'enum' => $intentValues,
                    ],
                    'minItems' => 0,
                    'maxItems' => count(IntentType::cases()),
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * JSON Schema for one {@see IntentKeyword} row.
     *
     * @return array<string, mixed>
     */
    protected function keywordDataJsonSchemaItem(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'keyword' => [
                    'type' => 'string',
                    'description' => 'Search query phrase',
                ],
                'relevance' => [
                    'type' => ['number', 'null'],
                    'description' => 'Relevance to the intent (0–1), or null',
                ],
            ],
            'required' => ['keyword', 'relevance'],
            'additionalProperties' => false,
        ];
    }

    protected function buildKeywordsPrompt(Intent $intentData): string
    {
        $payload = $intentData->toJson();

        return <<<PROMPT
You suggest concise search keywords (queries) that a user might type into a search engine to satisfy this intent.

Return distinct, non-redundant phrases. Prefer 2–5 words per keyword when reasonable. Avoid duplicates.

For each item, set "relevance" to a number between 0 and 1 indicating how well the keyword matches the intent, or null if you cannot score it.

Resolved intent (JSON):
{$payload}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildKeywordsJsonSchema(): array
    {
        $maxKeywords = (int) ($this->config['max_keywords'] ?? 25);

        return [
            'type' => 'object',
            'properties' => $properties = [
                'keywords' => [
                    'type' => 'array',
                    'description' => 'Relevant search queries for this intent with optional relevance scores',
                    'items' => $this->keywordDataJsonSchemaItem(),
                    'minItems' => 1,
                    'maxItems' => max(1, min(50, $maxKeywords)),
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    protected function checkForRefusal($response): void
    {
        foreach ($response->getOutput() as $item) {
            if (($item['type'] ?? null) === 'message' && isset($item['content'])) {
                foreach ($item['content'] as $content) {
                    if (($content['type'] ?? null) === 'refusal') {
                        $refusalMessage = $content['refusal'] ?? 'The model refused to complete this request.';
                        throw new RuntimeException(
                            "OpenAI refused the request: {$refusalMessage}"
                        );
                    }
                }
            }
        }
    }

    protected function parseIntentableIntentsResponse(string $responseText, Intentable $intentable): IntentableIntents
    {
        $data = json_decode($responseText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Failed to parse intent resolution response as JSON: '.json_last_error_msg()
            );
        }

        if (! is_array($data)) {
            throw new RuntimeException('Intent resolution JSON did not decode to an array');
        }

        $items = $data['intentable_intents'] ?? null;

        if (! is_array($items) || $items === []) {
            throw new RuntimeException('Intent resolution response must contain a non-empty "intentable_intents" array');
        }

        $rows = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['intent']) || ! is_array($item['intent'])) {
                continue;
            }

            $payload = [
                'intent' => $item['intent'],
                'intentable' => $intentable->toArray(),
                'relevance' => $item['relevance'] ?? null,
            ];

            try {
                $rows[] = IntentableIntent::fromArray($payload);
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        if ($rows === []) {
            throw new RuntimeException('Intent resolution produced no valid intentable_intents rows');
        }

        return (new IntentableIntents)->setIntentableIntents($rows);
    }

    /**
     * @return list<IntentKeyword>
     */
    protected function parseKeywordsResponse(string $responseText): array
    {
        $data = json_decode($responseText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Failed to parse keyword response as JSON: '.json_last_error_msg()
            );
        }

        $keywords = $data['keywords'] ?? [];

        if (! is_array($keywords)) {
            throw new RuntimeException('Keyword response must contain a "keywords" array');
        }

        $out = [];
        $seen = [];

        foreach ($keywords as $item) {
            if (! is_array($item)) {
                continue;
            }

            try {
                $row = IntentKeyword::fromArray($item);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $key = mb_strtolower($row->getKeyword());
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }
}
