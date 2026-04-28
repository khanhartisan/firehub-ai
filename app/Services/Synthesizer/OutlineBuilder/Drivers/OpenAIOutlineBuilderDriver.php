<?php

namespace App\Services\Synthesizer\OutlineBuilder\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Services\Synthesizer\OutlineBuilder\OutlineBuilderService;
use RuntimeException;

class OpenAIOutlineBuilderDriver extends OutlineBuilderService
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
        $this->config = array_merge(config('synthesizer.openai_outline_builder', []), $config);
    }

    public function outline(Brief $brief, ?SemanticContext $context): Outline
    {
        $payload = $this->generateOutlinePayload($brief, $context);

        $outline = new Outline;

        $title = trim((string) ($payload['title'] ?? ''));
        $outline->setTitle($title !== '' ? $title : ($brief->getTitle() ?: 'Untitled draft'));

        $items = $this->hydrateItems($payload['items'] ?? []);
        if ($items === []) {
            throw new RuntimeException('Failed to build outline with OpenAI: no valid outline items returned.');
        }
        $outline->setItems($items);

        return $outline;
    }

    protected function getModel(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-4o-mini');
    }

    protected function getTemperature(): float
    {
        return (float) ($this->config['temperature'] ?? 0.2);
    }

    protected function getMaxItems(): int
    {
        return (int) ($this->config['max_items'] ?? 20);
    }

    protected function getMaxDepth(): int
    {
        return (int) ($this->config['max_depth'] ?? 6);
    }

    /**
     * @return array<string, mixed>
     */
    protected function generateOutlinePayload(Brief $brief, ?SemanticContext $context): array
    {
        $data = $this->requestStructuredJson(
            $this->buildPrompt($brief, $context),
            'outline_build',
            $this->buildOutlineSchema(1),
            'Failed to build outline with OpenAI'
        );

        return is_array($data) ? $data : [];
    }

    protected function buildPrompt(Brief $brief, ?SemanticContext $context): string
    {
        $payload = [
            'brief' => $brief->toArray(),
            'semantic_context' => $context?->toArray(),
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are an editorial outline planner.

Given a writing brief and optional semantic context, generate a clear hierarchical article outline:
- Use concise section headings in point.headline.
- Provide short section summaries in point.description.
- Keep supporting facts/snippets in point.evidences.
- Put writing directives in guidelines (not in evidences).
- Keep structure logical and avoid duplication.
- Ground key section instructions in "semantic_context" when present.
- If "semantic_context.researched_points" is provided, use it as the primary evidence base:
  - Reorganize overlapping points into coherent sections.
  - Prioritize high-signal points that support the brief goals.
  - Omit points that are off-topic, weakly supported, redundant, or not useful for this article.
  - Do not force every researched point into the outline.
- Keep the outline reader-focused, concise, and publication-ready.

Input JSON:
{$json}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildOutlineSchema(int $depth): array
    {
        $itemSchema = [
            'type' => 'object',
            'description' => 'One outline section containing a core point, writing guidelines, and optional nested sub-points.',
            'properties' => [
                'point' => $this->buildRelevantPointSchema(),
                'guidelines' => [
                    'type' => 'array',
                    'description' => 'Actionable writing directives for this section (style, emphasis, structure, caveats).',
                    'items' => ['type' => 'string'],
                ],
                'sub_points' => [
                    'type' => 'array',
                    'description' => 'Supporting child points that should appear under this section.',
                    'maxItems' => $this->getMaxItems(),
                    'items' => $depth >= $this->getMaxDepth()
                        ? [
                            'type' => 'object',
                            'description' => 'Leaf-level outline section where no additional nesting is allowed.',
                            'properties' => [
                                'point' => $this->buildRelevantPointSchema(),
                                'guidelines' => [
                                    'type' => 'array',
                                    'description' => 'Actionable writing directives for this leaf section.',
                                    'items' => ['type' => 'string'],
                                ],
                                'sub_points' => [
                                    'type' => 'array',
                                    'description' => 'Must be empty at leaf depth.',
                                    'maxItems' => 0,
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => (object) [],
                                        'required' => [],
                                        'additionalProperties' => false,
                                    ],
                                ],
                            ],
                            'required' => ['point', 'guidelines', 'sub_points'],
                            'additionalProperties' => false,
                        ]
                        : $this->buildItemSchema($depth + 1),
                ],
            ],
            'required' => ['point', 'guidelines', 'sub_points'],
            'additionalProperties' => false,
        ];

        return [
            'type' => 'object',
            'properties' => $properties = [
                'title' => [
                    'type' => 'string',
                    'description' => 'Article outline title. Prefer concise, publication-ready phrasing.',
                ],
                'items' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => $this->getMaxItems(),
                    'description' => 'Top-level ordered outline sections.',
                    'items' => $itemSchema,
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildItemSchema(int $depth): array
    {
        $schema = [
            'type' => 'object',
            'description' => 'One nested outline section.',
            'properties' => [
                'point' => $this->buildRelevantPointSchema(),
                'guidelines' => [
                    'type' => 'array',
                    'description' => 'Actionable writing directives for this section.',
                    'items' => ['type' => 'string'],
                ],
                'sub_points' => [
                    'type' => 'array',
                    'description' => 'Supporting child points for this section.',
                    'maxItems' => $this->getMaxItems(),
                    'items' => $depth >= $this->getMaxDepth()
                        ? [
                            'type' => 'object',
                            'description' => 'Leaf-level nested section where no additional nesting is allowed.',
                            'properties' => [
                                'point' => $this->buildRelevantPointSchema(),
                                'guidelines' => [
                                    'type' => 'array',
                                    'description' => 'Actionable writing directives for this leaf section.',
                                    'items' => ['type' => 'string'],
                                ],
                                'sub_points' => [
                                    'type' => 'array',
                                    'description' => 'Must be empty at leaf depth.',
                                    'maxItems' => 0,
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => (object) [],
                                        'required' => [],
                                        'additionalProperties' => false,
                                    ],
                                ],
                            ],
                            'required' => ['point', 'guidelines', 'sub_points'],
                            'additionalProperties' => false,
                        ]
                        : $this->buildItemSchema($depth + 1),
                ],
            ],
            'required' => ['point', 'guidelines', 'sub_points'],
            'additionalProperties' => false,
        ];

        return $schema;
    }

    /**
     * @param  mixed  $rawItems
     * @return list<OutlineItem>
     */
    protected function hydrateItems(mixed $rawItems): array
    {
        if (! is_array($rawItems)) {
            return [];
        }

        $items = [];
        foreach ($rawItems as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rawPoint = $row['point'] ?? null;
            if (! is_array($rawPoint)) {
                continue;
            }

            $point = RelevantPoint::fromArray($rawPoint);
            if (trim((string) ($point->getHeadline() ?? '')) === '') {
                continue;
            }

            $item = (new OutlineItem)
                ->setPoint($point)
                ->setGuidelines($this->normalizeGuidelines($row['guidelines'] ?? []))
                ->setSubPoints($this->hydrateSubPoints($row['sub_points'] ?? []));

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param  mixed  $rawSubPoints
     * @return list<RelevantPoint>
     */
    protected function hydrateSubPoints(mixed $rawSubPoints): array
    {
        if (! is_array($rawSubPoints)) {
            return [];
        }

        $points = [];
        foreach ($rawSubPoints as $entry) {
            if (is_array($entry) && isset($entry['point']) && is_array($entry['point'])) {
                $entry = $entry['point'];
            }

            if (! is_array($entry)) {
                continue;
            }

            $point = RelevantPoint::fromArray($entry);
            if (trim((string) ($point->getHeadline() ?? '')) !== '') {
                $points[] = $point;
            }
        }

        return $points;
    }

    /**
     * @param  mixed  $rawGuidelines
     * @return list<string>
     */
    protected function normalizeGuidelines(mixed $rawGuidelines): array
    {
        if (! is_array($rawGuidelines)) {
            return [];
        }

        $guidelines = [];
        foreach ($rawGuidelines as $line) {
            $text = trim((string) $line);
            if ($text !== '') {
                $guidelines[] = $text;
            }
        }

        return array_values(array_unique($guidelines));
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRelevantPointSchema(): array
    {
        return [
            'type' => 'object',
            'description' => 'Core point data for an outline section.',
            'properties' => [
                'headline' => [
                    'type' => 'string',
                    'description' => 'Short, clear section heading that states the key idea.',
                ],
                'description' => [
                    'type' => ['string', 'null'],
                    'description' => 'Brief summary of what the section should cover.',
                ],
                'evidences' => [
                    'type' => 'array',
                    'description' => 'Facts, supporting details, or source-grounded snippets to include in this section.',
                    'items' => ['type' => 'string'],
                ],
                'relevance' => [
                    'type' => ['number', 'null'],
                    'minimum' => 0,
                    'maximum' => 1,
                    'description' => 'Optional relevance score for this point relative to the brief/context (0..1).',
                ],
                'rationale' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional short reason why this point is strategically useful.',
                ],
            ],
            'required' => ['headline', 'description', 'evidences', 'relevance', 'rationale'],
            'additionalProperties' => false,
        ];
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
