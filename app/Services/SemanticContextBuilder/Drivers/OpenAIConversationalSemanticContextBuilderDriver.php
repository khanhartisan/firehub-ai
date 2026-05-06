<?php

namespace App\Services\SemanticContextBuilder\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\SemanticContextBuilder\ConversationalSemanticContextBuilder;
use App\Utils\Json;
use RuntimeException;

class OpenAIConversationalSemanticContextBuilderDriver implements ConversationalSemanticContextBuilder
{
    protected SemanticContext $context;

    /** @var array<int, array{role: string, text: string}> */
    protected array $conversation = [];

    /** @var string[] */
    protected array $pendingQuestions = [];

    protected bool $isFulfilled = false;

    /** @var array<string, mixed> */
    protected array $config;

    public function __construct(
        protected ?OpenAIClient $openAIClient = null,
        array $config = []
    ) {
        $this->config = array_merge(config('semantic_context_builder.drivers.openai', []), $config);
        $this->setContext(new SemanticContext());
    }

    public function setContext(SemanticContext $context): static
    {
        $this->context = $context->withEmptyFields(true);
        $this->isFulfilled = false;
        $this->pendingQuestions = [];

        return $this;
    }

    public function getContext(): SemanticContext
    {
        return $this->context;
    }

    public function start(string $seedMessage): static
    {
        $this->conversation = [];
        $this->isFulfilled = false;
        $this->pendingQuestions = [];

        return $this->continueWith($seedMessage);
    }

    public function continueWith(string $userMessage): static
    {
        $message = trim($userMessage);
        if ($message === '') {
            return $this;
        }

        $this->conversation[] = [
            'role' => 'user',
            'text' => $message,
        ];

        $step = $this->requestAssistantStep();
        $this->applyAssistantStep($step);

        return $this;
    }

    public function isFulfilled(): bool
    {
        return $this->isFulfilled;
    }

    public function getNextQuestion(): ?string
    {
        return $this->pendingQuestions[0] ?? null;
    }

    public function getPendingQuestions(): array
    {
        return $this->pendingQuestions;
    }

    public function getConversation(): array
    {
        return $this->conversation;
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestAssistantStep(): array
    {
        if (! $this->openAIClient instanceof OpenAIClient) {
            throw new RuntimeException('OpenAI client is not configured for semantic context builder.');
        }

        $payload = [
            'semantic_context' => $this->context->toArray(),
            'conversation' => $this->conversation,
        ];

        $json = Json::encode($payload);

        $prompt = <<<PROMPT
You are a context-building assistant.

Your goal is to iteratively fill the provided semantic context by asking concise follow-up questions and proposing structured updates.

Rules:
- Only use keys that already exist in semantic_context.
- suggested_updates must be an array of objects with {key, value}.
- value must be primitive-compatible: string, number, null, or array of strings.
- Keep questions short and specific.
- Set is_fulfilled=true only when the context is sufficiently complete for practical use.

Input JSON:
{$json}
PROMPT;

        $response = $this->openAIClient->createResponse(
            ResponseInput::text($prompt),
            ResponseOptions::create()
                ->model($this->getModel())
                ->temperature($this->getTemperature())
                ->responseFormat([
                    'type' => 'json_schema',
                    'name' => 'semantic_context_builder_step',
                    'schema' => $this->buildStepSchema(),
                    'strict' => true,
                ])
        );

        $text = $response->getFirstOutputText();
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('OpenAI returned empty builder response.');
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned invalid builder JSON: '.json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $step
     */
    protected function applyAssistantStep(array $step): void
    {
        $assistantMessage = trim((string) ($step['assistant_message'] ?? ''));
        if ($assistantMessage !== '') {
            $this->conversation[] = [
                'role' => 'assistant',
                'text' => $assistantMessage,
            ];
        }

        $this->pendingQuestions = [];
        if (is_array($step['questions'] ?? null)) {
            $this->pendingQuestions = array_values(array_filter(
                array_map(static fn (mixed $q): string => trim((string) $q), $step['questions']),
                static fn (string $q): bool => $q !== ''
            ));
        }

        $suggestedUpdates = is_array($step['suggested_updates'] ?? null) ? $step['suggested_updates'] : [];
        foreach ($suggestedUpdates as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = trim((string) ($entry['key'] ?? ''));
            if ($key === '' || ! $this->context->has($key)) {
                continue;
            }

            $value = $entry['value'] ?? null;
            if (is_string($value) && strtolower(trim($value)) === 'null') {
                $value = null;
            }

            if (is_numeric($value) && ! is_int($value) && ! is_float($value)) {
                $value = str_contains((string) $value, '.') ? (float) $value : (int) $value;
            }

            if (is_array($value)) {
                $value = array_values(array_filter(
                    array_map(static fn (mixed $item): string => trim((string) $item), $value),
                    static fn (string $item): bool => $item !== ''
                ));
            }

            if (! $this->isSupportedUpdateValue($value)) {
                continue;
            }

            $description = $this->context->getDescription($key) ?? ('Value for '.$key.'.');
            $this->context->set($key, $description, $this->normalizeUpdateValue($value));
        }

        $this->isFulfilled = (bool) ($step['is_fulfilled'] ?? false);
    }

    protected function getModel(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-4o-mini');
    }

    protected function getTemperature(): float
    {
        return (float) ($this->config['temperature'] ?? 0.2);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStepSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $properties = [
                'assistant_message' => ['type' => 'string'],
                'is_fulfilled' => ['type' => 'boolean'],
                'questions' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'suggested_updates' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => ['type' => 'string'],
                            'value' => [
                                'type' => ['string', 'number', 'null', 'array'],
                                'items' => ['type' => 'string'],
                            ],
                        ],
                        'required' => ['key', 'value'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    protected function isSupportedUpdateValue(mixed $value): bool
    {
        if ($value === null || is_string($value) || is_int($value) || is_float($value)) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_string($item)) {
                return false;
            }
        }

        return true;
    }

    protected function normalizeUpdateValue(mixed $value): string|int|float|array|null
    {
        if (is_array($value)) {
            return array_values(array_filter(
                array_map(static fn (string $item): string => trim($item), $value),
                static fn (string $item): bool => $item !== ''
            ));
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        }

        return $value;
    }
}
