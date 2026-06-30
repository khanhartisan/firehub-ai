<?php

namespace App\Services\Synthesizer\BriefBuilder\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\CommonData\AudienceContext;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Enums\ContentGoal;
use App\Enums\ContentTone;
use App\Enums\ContentVoice;
use App\Enums\Country;
use App\Enums\KnowledgeLevel;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;
use App\Services\Synthesizer\BriefBuilder\BriefBuilderService;
use RuntimeException;

class OpenAIBriefBuilderDriver extends BriefBuilderService
{
    protected ?OpenAIClient $openAIClient;

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(?OpenAIClient $openAIClient = null, array $config = [])
    {
        $this->openAIClient = $openAIClient;
        $this->config = array_merge(SynthesizerSubserviceConfig::settings('brief_builder'), $config);
    }

    public function conceive(Idea $idea, SemanticContext $context): Brief
    {
        $intent = $idea->getIntent();
        $fallbackDescription = $this->resolveFallbackDescription($context);
        $defaultInstructions = array_values(array_filter([
            'Keep claims grounded in source context.',
            $idea->getReason(),
        ]));

        $brief = (new Brief)
            ->setTemporal($intent->getTemporal())
            ->setTitle($intent->getTitle())
            ->setDescription(trim((string) $intent->getDescription()))
            ->setInstructions($defaultInstructions);

        if ($this->openAIClient instanceof OpenAIClient) {
            try {
                $result = $this->generateBriefPayload($idea, $context);
            } catch (RuntimeException) {
                $result = null;
            }

            if ($result !== null) {
                $brief = $this->hydrateBriefFromPayload($brief, $result);
            }
        }

        if (trim((string) $brief->getTitle()) === '') {
            $brief->setTitle($intent->getTitle());
        }

        if (trim((string) $brief->getDescription()) === '') {
            $brief->setDescription($fallbackDescription);
        }

        if ($brief->getInstructions() === []) {
            $brief->setInstructions($defaultInstructions);
        }

        return $brief;
    }

    protected function getModel(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-4o-mini');
    }

    protected function getTemperature(): float
    {
        return (float) ($this->config['temperature'] ?? 0.2);
    }

    protected function getMaxInstructions(): int
    {
        return max(1, min(12, (int) ($this->config['max_instructions'] ?? 6)));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function generateBriefPayload(Idea $idea, SemanticContext $context): ?array
    {
        $prompt = $this->buildPrompt($idea, $context);
        $schema = $this->buildBriefSchema();
        $data = $this->requestStructuredJson(
            $prompt,
            'brief_conceive',
            $schema,
            'Failed to build brief with OpenAI',
        );

        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        if ($title === '' || $description === '') {
            return null;
        }

        return $data;
    }

    protected function buildPrompt(Idea $idea, SemanticContext $context): string
    {
        $payload = [
            'idea' => $idea->toArray(),
            'semantic_context' => $context->toArray(),
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are drafting a concise writing brief for an editorial pipeline.

Given an article idea and semantic context, produce a full brief payload:
- title: specific and publishable.
- description: clear 1-3 sentence brief.
- temporal/goal/voice/tone: choose best enums from allowed values.
- instructions: concise, actionable bullet-style lines.
- audience_contexts/reference_page_ids: include when confidently inferable, otherwise [].
- For audience_contexts, follow the schema exactly. Do not output unknown keys.
- Keep all fields grounded in provided context and do not invent unsupported facts.

Your job is to review the initial idea, and the researched data, then give the final finest brief that will be possible to base on the researched data to build the final article.
That means if the initial data isn't fit with the provided researched data, you may slightly change the title/description and even other guidelines, to fit the best with the researched data.

Input JSON:
{$json}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildBriefSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $properties = [
                'temporal' => [
                    'type' => 'string',
                    'enum' => array_map(static fn (Temporal $temporal): string => $temporal->value, Temporal::cases()),
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Title for the article brief.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Concise brief description for the article.',
                ],
                'goal' => [
                    'type' => 'string',
                    'enum' => array_map(static fn (ContentGoal $goal): string => $goal->value, ContentGoal::cases()),
                ],
                'voice' => [
                    'type' => 'string',
                    'enum' => array_map(static fn (ContentVoice $voice): string => $voice->value, ContentVoice::cases()),
                ],
                'tone' => [
                    'type' => 'string',
                    'enum' => array_map(static fn (ContentTone $tone): string => $tone->value, ContentTone::cases()),
                ],
                'instructions' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => $this->getMaxInstructions(),
                    'items' => ['type' => 'string'],
                    'description' => 'Actionable instructions for authoring this article.',
                ],
                'audience_contexts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'description' => 'Audience context payload compatible with AudienceContext semantic keys.',
                        'properties' => $properties = $this->buildAudienceSchemaProperties(),
                        'required' => array_keys($properties),
                        'additionalProperties' => false,
                    ],
                ],
                'reference_page_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hydrateBriefFromPayload(Brief $brief, array $payload): Brief
    {
        $brief->setTitle(trim((string) ($payload['title'] ?? '')));
        $brief->setDescription(trim((string) ($payload['description'] ?? '')));

        $temporal = Temporal::tryFrom((string) ($payload['temporal'] ?? ''));
        if ($temporal instanceof Temporal) {
            $brief->setTemporal($temporal);
        }

        $goal = ContentGoal::tryFrom((string) ($payload['goal'] ?? ''));
        if ($goal instanceof ContentGoal) {
            $brief->setGoal($goal);
        }

        $voice = ContentVoice::tryFrom((string) ($payload['voice'] ?? ''));
        if ($voice instanceof ContentVoice) {
            $brief->setVoice($voice);
        }

        $tone = ContentTone::tryFrom((string) ($payload['tone'] ?? ''));
        if ($tone instanceof ContentTone) {
            $brief->setTone($tone);
        }

        $instructions = [];
        $rawInstructions = $payload['instructions'] ?? [];
        if (is_array($rawInstructions)) {
            foreach ($rawInstructions as $line) {
                $text = trim((string) $line);
                if ($text !== '') {
                    $instructions[] = $text;
                }
            }
        }
        $brief->setInstructions(array_values(array_unique($instructions)));

        $rawAudienceContexts = $payload['audience_contexts'] ?? [];
        if (is_array($rawAudienceContexts)) {
            $audienceContexts = [];
            foreach ($rawAudienceContexts as $row) {
                if (is_array($row)) {
                    $context = new AudienceContext();
                    foreach ($row as $key => $value) {
                        if (! is_string($key)) {
                            continue;
                        }
                        $description = $context->getDescription($key) ?? ('Audience context field: '.$key);
                        $context->set($key, $description, $value);
                    }
                    $audienceContexts[] = $context;
                }
            }
            $brief->setAudienceContexts($audienceContexts);
        }

        $rawReferencePageIds = $payload['reference_page_ids'] ?? [];
        if (is_array($rawReferencePageIds)) {
            $ids = [];
            foreach ($rawReferencePageIds as $id) {
                $sid = trim((string) $id);
                if ($sid !== '') {
                    $ids[] = $sid;
                }
            }
            $brief->setReferencePageIds(array_values(array_unique($ids)));
        }

        return $brief;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildAudienceSchemaProperties(): array
    {
        return [
            'priority_weight' => [
                'type' => ['number', 'null'],
                'minimum' => 0,
                'maximum' => 1,
            ],
            'name' => ['type' => ['string', 'null']],
            'description' => ['type' => ['string', 'null']],
            'age_from' => [
                'type' => ['integer', 'null'],
                'minimum' => 0,
                'maximum' => 120,
            ],
            'age_to' => [
                'type' => ['integer', 'null'],
                'minimum' => 0,
                'maximum' => 120,
            ],
            'knowledge_level' => [
                'type' => ['string', 'null'],
                'enum' => array_merge(
                    array_map(static fn (KnowledgeLevel $level): string => $level->value, KnowledgeLevel::cases()),
                    [null]
                ),
            ],
            'language' => [
                'type' => ['string', 'null'],
                'enum' => array_merge(
                    array_map(static fn (Language $language): string => $language->value, Language::cases()),
                    [null]
                ),
            ],
            'countries' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => array_map(static fn (Country $country): string => $country->value, Country::cases()),
                ],
            ],
            'pain_points' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'concerns' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'aspirations' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'fears' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
        ];
    }

    protected function resolveFallbackDescription(SemanticContext $context): string
    {
        $articleContext = $context->getArticleContextValue();
        if (is_string($articleContext) || is_int($articleContext) || is_float($articleContext)) {
            return trim((string) $articleContext);
        }

        if (is_array($articleContext)) {
            $rawText = $articleContext['meta']['value']['raw_text'] ?? null;
            if (is_string($rawText)) {
                return trim($rawText);
            }

            return trim(json_encode($articleContext, JSON_UNESCAPED_UNICODE) ?: '');
        }

        $description = $context->getDescriptionValue();

        return is_string($description) ? trim($description) : '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestStructuredJson(
        string $prompt,
        string $schemaName,
        array $jsonSchema,
        string $failureMessage,
    ): array {
        if (! $this->openAIClient instanceof OpenAIClient) {
            throw new RuntimeException("{$failureMessage}: OpenAI client is not configured.");
        }

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
}
