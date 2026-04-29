<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\IdeaAdvisorService;
use RuntimeException;

/**
 * Uses the OpenAI Responses API with structured JSON output to rank temporals,
 * intent types, and brainstorm {@see Idea} instances from client context.
 */
class OpenAIIdeaAdvisorDriver extends IdeaAdvisorService
{
    protected OpenAIClient $openAIClient;

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(OpenAIClient $openAIClient, array $config = [])
    {
        $this->openAIClient = $openAIClient;
        $this->config = array_merge(config('synthesizer.openai_idea_advisor', []), $config);

        $this->setIdentifier((string) ($this->config['identifier'] ?? 'openai-idea-advisor'));
        $this->setDescription((string) ($this->config['description'] ?? 'OpenAI-backed advisor for temporal, intent-type, and idea suggestions.'));
    }

    public function suggestTemporal(string $clientId, SemanticContext $context): array
    {
        $prompt = $this->buildSuggestTemporalPrompt($clientId, $context);
        $schema = $this->buildSuggestTemporalJsonSchema();
        $data = $this->requestStructuredJson(
            $prompt,
            'suggest_temporals',
            $schema,
            'Failed to suggest temporals with OpenAI'
        );

        $rows = $data['temporal_suggestions'] ?? null;
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('OpenAI temporal suggestions response must contain a non-empty "temporal_suggestions" array.');
        }

        $suggestions = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $temporal = isset($row['temporal']) ? Temporal::tryFrom((string) $row['temporal']) : null;
            if (! $temporal instanceof Temporal) {
                continue;
            }
            $confidence = isset($row['confidence']) ? (float) $row['confidence'] : null;
            $reason = isset($row['reason']) ? (string) $row['reason'] : null;
            $suggestions[] = new TemporalSuggestion($temporal, $confidence, $reason);
        }

        if ($suggestions === []) {
            throw new RuntimeException('OpenAI returned no valid temporal suggestions.');
        }

        usort(
            $suggestions,
            static fn (TemporalSuggestion $a, TemporalSuggestion $b): int => (float) ($b->getConfidence() ?? 0) <=> (float) ($a->getConfidence() ?? 0)
        );

        return $suggestions;
    }

    public function suggestIntentTypes(string $clientId, SemanticContext $context): array
    {
        $prompt = $this->buildSuggestIntentTypesPrompt($clientId, $context);
        $schema = $this->buildSuggestIntentTypesJsonSchema();
        $data = $this->requestStructuredJson(
            $prompt,
            'suggest_intent_types',
            $schema,
            'Failed to suggest intent types with OpenAI'
        );

        $rows = $data['intent_type_suggestions'] ?? null;
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('OpenAI intent-type suggestions response must contain a non-empty "intent_type_suggestions" array.');
        }

        $suggestions = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $intentType = isset($row['intent_type']) ? IntentType::tryFrom((int) $row['intent_type']) : null;
            if (! $intentType instanceof IntentType) {
                continue;
            }
            $confidence = isset($row['confidence']) ? (float) $row['confidence'] : null;
            $reason = isset($row['reason']) ? (string) $row['reason'] : null;
            $suggestions[] = new IntentTypeSuggestion($intentType, $confidence, $reason);
        }

        if ($suggestions === []) {
            throw new RuntimeException('OpenAI returned no valid intent type suggestions.');
        }

        usort(
            $suggestions,
            static fn (IntentTypeSuggestion $a, IntentTypeSuggestion $b): int => (float) ($b->getConfidence() ?? 0) <=> (float) ($a->getConfidence() ?? 0)
        );

        return $suggestions;
    }

    /**
     * @throws \JsonException
     */
    public function brainstorm(
        array $temporalSuggestions,
        array $intentTypeSuggestions,
        SemanticContext $context,
        int $limit = 5
    ): array {
        $limit = max(1, min(20, $limit));
        $temporals = array_values(array_filter($temporalSuggestions, static fn ($item) => $item instanceof TemporalSuggestion));
        $intentTypes = array_values(array_filter($intentTypeSuggestions, static fn ($item) => $item instanceof IntentTypeSuggestion));

        if ($temporals === [] || $intentTypes === []) {
            return [];
        }

        $prompt = $this->buildBrainstormPrompt($temporals, $intentTypes, $context, $limit);
        $schema = $this->buildBrainstormJsonSchema($limit);
        $data = $this->requestStructuredJson(
            $prompt,
            'brainstorm_ideas',
            $schema,
            'Failed to brainstorm ideas with OpenAI'
        );

        $rows = $data['ideas'] ?? null;
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $ideas = [];
        foreach ($rows as $row) {
            if (! is_array($row) || count($ideas) >= $limit) {
                break;
            }

            $temporal = isset($row['temporal']) ? Temporal::tryFrom((string) $row['temporal']) : null;
            $intentType = isset($row['intent_type']) ? IntentType::tryFrom((int) $row['intent_type']) : null;
            if (! $temporal instanceof Temporal || ! $intentType instanceof IntentType) {
                continue;
            }

            $title = isset($row['title']) ? trim((string) $row['title']) : '';
            $description = isset($row['description']) ? trim((string) $row['description']) : '';
            if ($title === '' || $description === '') {
                continue;
            }

            $intent = (new Intent)
                ->setTitle($title)
                ->setDescription($description)
                ->setTemporal($temporal)
                ->setLanguage(Language::EN)
                ->setTypes([$intentType]);

            $confidence = isset($row['confidence']) ? max(0.0, min(1.0, (float) $row['confidence'])) : null;
            $reason = isset($row['reason']) ? (string) $row['reason'] : null;

            $ideas[] = new Idea($intent, $confidence, $reason);
        }

        return $ideas;
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestStructuredJson(string $prompt, string $schemaName, array $jsonSchema, string $failureMessage): array
    {
        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->getModel())
            ->temperature($this->getTemperature())
            ->responseFormat([
                'type' => 'json_schema',
                'name' => $schemaName,
                'schema' => $jsonSchema,
                'strict' => true,
            ]);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "{$failureMessage}: {$e->getMessage()}",
                0,
                $e
            );
        }

        $this->checkForRefusal($response);

        $text = $response->getFirstOutputText();
        if ($text === null || $text === '') {
            throw new RuntimeException("{$failureMessage}: empty model output.");
        }

        $data = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            throw new RuntimeException(
                "{$failureMessage}: invalid JSON (".json_last_error_msg().').'
            );
        }

        return $data;
    }

    protected function getModel(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-4o-mini');
    }

    protected function getTemperature(): float
    {
        return (float) ($this->config['temperature'] ?? 0.3);
    }

    protected function getMaxTemporalSuggestions(): int
    {
        return max(1, min(20, (int) ($this->config['max_temporal_suggestions'] ?? 8)));
    }

    protected function getMaxIntentTypeSuggestions(): int
    {
        return max(1, min(20, (int) ($this->config['max_intent_type_suggestions'] ?? 8)));
    }

    protected function checkForRefusal(Response $response): void
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

    protected function buildSuggestTemporalPrompt(string $clientId, SemanticContext $context): string
    {
        $contextJson = $this->encodeContext($context);
        $lines = [];
        foreach (Temporal::cases() as $case) {
            $lines[] = sprintf('- %s: %s', $case->value, Temporal::describe($case));
        }
        $catalog = implode("\n", $lines);

        return <<<PROMPT
You help plan content for a publishing pipeline. Given a client id and their editorial/business context, suggest ranked temporal angles (how time-sensitive or evergreen the next piece should feel).

Allowed temporal values (use these exact strings in output):
{$catalog}

Client id: {$clientId}

Context:
{$contextJson}

Return JSON only (via schema). Order suggestions by usefulness; confidence is 0–1; reason is one short sentence.
PROMPT;
    }

    protected function buildSuggestIntentTypesPrompt(string $clientId, SemanticContext $context): string
    {
        $contextJson = $this->encodeContext($context);
        $lines = [];
        foreach (IntentType::cases() as $case) {
            $lines[] = sprintf('- %d: %s', $case->value, IntentType::describe($case));
        }
        $catalog = implode("\n", $lines);

        return <<<PROMPT
You help plan search-intent coverage for content. Given a client id and context, suggest ranked search intent types for the next article idea.

Allowed intent_type integers (use these exact numbers in output):
{$catalog}

Client id: {$clientId}

Context:
{$contextJson}

Return JSON only (via schema). Order by relevance; confidence is 0–1; reason is one short sentence.
PROMPT;
    }

    /**
     * @param  list<TemporalSuggestion>  $temporals
     * @param  list<IntentTypeSuggestion>  $intentTypes
     */
    protected function buildBrainstormPrompt(array $temporals, array $intentTypes, SemanticContext $context, int $limit): string
    {
        $temporalPayload = array_map(static fn (TemporalSuggestion $t) => $t->toArray(), $temporals);
        $intentPayload = array_map(static fn (IntentTypeSuggestion $t) => $t->toArray(), $intentTypes);

        $temporalJson = json_encode($temporalPayload, JSON_THROW_ON_ERROR);
        $intentJson = json_encode($intentPayload, JSON_THROW_ON_ERROR);

        $contextJson = $this->encodeContext($context);

        return <<<PROMPT
You propose concrete article ideas for a content pipeline. Use the ranked temporal and intent-type suggestions below as guidance. Each idea must pick one temporal and one intent_type from those suggestions (or their enum values) and include a compelling title and short description aligned with the context.

Temporal suggestions (JSON):
{$temporalJson}

Intent type suggestions (JSON):
{$intentJson}

Editorial / business context:
{$contextJson}

Ensure all ideas are mutually distinct. Do not propose duplicate concepts. Do not generate generic clickbait.

Contextual Balance (Continuity vs. Exploration): Review the "Recent Articles History" to understand the current narrative phase. You SHOULD provide a diverse ideas that either, or balance between:
- Continuity Ideas: Topics that logically advance the recent content and match the "Audience Knowledge Stage".
- Exploration Ideas: Fresh, tangential, or entirely new angles that diversify the website's content while still strictly respecting the overarching "Editorial / business context".


Your duty is to make sure that the new idea will bring the better engagement and best experience for the reader of the client's context.

Return up to {$limit} distinct ideas as JSON (via schema). Confidence is 0–1 for how strong the idea is given the inputs.
PROMPT;
    }

    protected function encodeContext(SemanticContext $context): string
    {
        return json_encode($context->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSuggestTemporalJsonSchema(): array
    {
        $max = $this->getMaxTemporalSuggestions();
        $enum = array_map(static fn (Temporal $t) => $t->value, Temporal::cases());

        return [
            'type' => 'object',
            'properties' => $properties = [
                'temporal_suggestions' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => $max,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'temporal' => [
                                'type' => 'string',
                                'enum' => $enum,
                            ],
                            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['temporal', 'confidence', 'reason'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSuggestIntentTypesJsonSchema(): array
    {
        $max = $this->getMaxIntentTypeSuggestions();
        $enum = array_map(static fn (IntentType $t) => $t->value, IntentType::cases());

        return [
            'type' => 'object',
            'properties' => $properties = [
                'intent_type_suggestions' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => $max,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'intent_type' => [
                                'type' => 'integer',
                                'enum' => $enum,
                            ],
                            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['intent_type', 'confidence', 'reason'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildBrainstormJsonSchema(int $limit): array
    {
        $temporalEnum = array_map(static fn (Temporal $t) => $t->value, Temporal::cases());
        $intentEnum = array_map(static fn (IntentType $t) => $t->value, IntentType::cases());

        return [
            'type' => 'object',
            'properties' => $properties = [
                'ideas' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => $limit,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'temporal' => [
                                'type' => 'string',
                                'enum' => $temporalEnum,
                            ],
                            'intent_type' => [
                                'type' => 'integer',
                                'enum' => $intentEnum,
                            ],
                            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['title', 'description', 'temporal', 'intent_type', 'confidence', 'reason'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }
}
