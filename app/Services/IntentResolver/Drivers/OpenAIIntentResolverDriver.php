<?php

namespace App\Services\IntentResolver\Drivers;

use App\Contracts\IntentResolver\IntentData;
use App\Contracts\IntentResolver\IntentKeywordData;
use App\Contracts\IntentResolver\IntentResolver;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Enums\IntentType;
use App\Enums\Language;
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

    public function resolve(string $content): IntentData
    {
        $content = $this->prepareContent($content);

        $prompt = $this->buildResolvePrompt($content);
        $jsonSchema = $this->buildIntentJsonSchema();

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->defaultModel)
            ->responseFormat([
                'type' => 'json_schema',
                'name' => 'search_intent',
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

        return $this->parseIntentResponse($responseText);
    }

    /**
     * @return list<IntentKeywordData>
     */
    public function guessKeywords(IntentData $intentData): array
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

    protected function buildResolvePrompt(string $content): string
    {
        $typeLines = [];
        foreach (IntentType::cases() as $type) {
            $typeLines[] = sprintf('- %d: %s', $type->value, IntentType::describe($type));
        }
        $typeGuide = implode("\n", $typeLines);

        return <<<PROMPT
You are a Senior SEO Content Architect and User Intent Analyst.

You analyze text and infer the user's search intent for SEO / keyword research.

Classify the content using one or more intent types (use the numeric codes below). You may assign short human-readable title and description fields summarizing the intent.

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
- "types" may include multiple values when the content clearly fits more than one intent.
- Use "unknown" (6) only when the intent cannot be determined.
- Prefer specific intents over UNKNOWN when possible.

Content:
{$content}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildIntentJsonSchema(): array
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

    protected function buildKeywordsPrompt(IntentData $intentData): string
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
                    'items' => [
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
                    ],
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

    protected function parseIntentResponse(string $responseText): IntentData
    {
        $data = json_decode($responseText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Failed to parse intent response as JSON: '.json_last_error_msg()
            );
        }

        if (! is_array($data)) {
            throw new RuntimeException('Intent response JSON did not decode to an array');
        }

        return IntentData::fromArray($data);
    }

    /**
     * @return list<IntentKeywordData>
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
                $row = IntentKeywordData::fromArray($item);
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
