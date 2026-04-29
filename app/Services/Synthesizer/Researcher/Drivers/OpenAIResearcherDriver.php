<?php

namespace App\Services\Synthesizer\Researcher\Drivers;

use App\Contracts\CommonData\Fact;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\Researcher\ConflictedPoints;
use App\Contracts\Synthesizer\Researcher\ConsolidationResult;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Services\Synthesizer\Researcher\ResearcherService;
use RuntimeException;

class OpenAIResearcherDriver extends ResearcherService
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
        $this->config = array_merge(config('synthesizer.openai_researcher', []), $config);
    }

    public function extractIdeaPoints(Idea $idea, string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        $prompt = $this->buildExtractPointsPrompt($idea, $content);
        $schema = $this->buildExtractPointsJsonSchema();
        $data = $this->requestStructuredJson(
            $prompt,
            'research_extract_points',
            $schema,
            'Failed to extract research points with OpenAI'
        );

        $rows = $data['points'] ?? [];
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $points = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $headline = isset($row['headline']) ? trim((string) $row['headline']) : '';
            $description = isset($row['description']) ? trim((string) $row['description']) : '';
            if ($headline === '' || $description === '') {
                continue;
            }

            $evidences = [];
            if (isset($row['evidences']) && is_array($row['evidences'])) {
                foreach ($row['evidences'] as $evidence) {
                    $line = trim((string) $evidence);
                    if ($line !== '') {
                        $evidences[] = $line;
                    }
                }
            }

            $rationale = isset($row['rationale']) ? trim((string) $row['rationale']) : null;
            if ($rationale === '') {
                $rationale = null;
            }

            $relevance = isset($row['relevance']) ? (float) $row['relevance'] : null;

            $points[] = (new RelevantPoint)
                ->setHeadline($headline)
                ->setDescription($description)
                ->setEvidences($evidences)
                ->setRationale($rationale)
                ->setRelevance($relevance);
        }

        return $points;
    }

    /**
     * @throws \JsonException
     */
    public function consolidateIdeaPoints(Idea $idea, array $points): ConsolidationResult
    {
        $relevantPoints = array_values(array_filter(
            $points,
            static fn (mixed $point): bool => $point instanceof RelevantPoint
        ));
        if ($relevantPoints === []) {
            return new ConsolidationResult;
        }

        $prompt = $this->buildConsolidatePointsPrompt($idea, $relevantPoints);
        $schema = $this->buildConsolidatePointsJsonSchema();
        $data = $this->requestStructuredJson(
            $prompt,
            'research_consolidate_points',
            $schema,
            'Failed to consolidate research points with OpenAI'
        );

        $result = new ConsolidationResult;

        $resolvedRows = $data['points'] ?? [];
        if (is_array($resolvedRows)) {
            $resolvedPoints = [];
            foreach ($resolvedRows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $resolvedPoints[] = RelevantPoint::fromArray($row);
            }
            $result->setPoints($resolvedPoints);
        }

        $conflictRows = $data['conflicts'] ?? [];
        if (is_array($conflictRows)) {
            $conflicts = [];
            foreach ($conflictRows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $conflicts[] = ConflictedPoints::fromArray($row);
            }
            $result->setConflicts($conflicts);
        }

        return $result;
    }

    /**
     * @throws \JsonException
     */
    public function resolveIdeaConflictedPoints(
        Idea $idea,
        ConflictedPoints $conflictedPoints,
        array $facts
    ): RelevantPoint {
        $prompt = $this->buildResolveConflictedPointsPrompt($idea, $conflictedPoints, $facts);
        $schema = $this->buildResolveConflictedPointsJsonSchema();
        $data = $this->requestStructuredJson(
            $prompt,
            'research_resolve_conflicted_points',
            $schema,
            'Failed to resolve conflicted points with OpenAI'
        );

        if (! isset($data['point']) || ! is_array($data['point'])) {
            throw new RuntimeException('Failed to resolve conflicted points with OpenAI: missing point output.');
        }

        return RelevantPoint::fromArray($data['point']);
    }

    protected function getModel(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-4o-mini');
    }

    protected function getTemperature(): float
    {
        return (float) ($this->config['temperature'] ?? 0.2);
    }

    protected function getMaxPoints(): int
    {
        return (int) ($this->config['max_points'] ?? 20);
    }

    protected function buildExtractPointsPrompt(Idea $idea, string $content): string
    {
        $ideaJson = json_encode($idea->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a research analyst for editorial planning. You will be provided an idea of the new article, and a source content that may contain related information for the article.
 
Your job is to extract points from the source content that is related to the provided idea.

You should not include the points that are not related, non-relevant to the provided idea.

Each point should include:
- headline: short and punchy
- description: concise explanation
- evidences: All facts/quotes/snippets/numbers/analytics/benchmark/information... from source content that supports or is related to the point.
- rationale: why this point is strategically relevant to the provided idea
- relevance: 0..1 relevance to the idea, 0 is not relevant, 1 is extremely relevant.

It is very important to extract the real numbers, analytics, examples, proofs... of the point into the evidences.

Idea JSON:
{$ideaJson}

Source content:
{$content}

Return JSON only (via schema), ordered by relevance descending.
PROMPT;
    }

    /**
     * @throws \JsonException
     */
    protected function buildResolveConflictedPointsPrompt(
        Idea $idea,
        ConflictedPoints $conflictedPoints,
        array $facts
    ): string {
        $ideaJson = json_encode($idea->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $conflictsJson = json_encode($conflictedPoints->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $factsJson = json_encode($this->normalizeFacts($facts), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a research resolver.

Given:
- one editorial idea
- one conflicted points group
- verified facts (source of truth)

Return exactly one resolved relevant point that:
- aligns with verified facts
- keeps only evidence supported by verified facts
- has concise rationale
- includes relevance (to the given idea) in [0,1]

It is very important to keep the real numbers, analytics, examples, proofs... of the point in the evidences.

Idea JSON:
{$ideaJson}

Conflicted points JSON:
{$conflictsJson}

Verified facts JSON:
{$factsJson}

Return JSON only using the provided schema.
PROMPT;
    }

    /**
     * @param  array<int, RelevantPoint>  $points
     */
    protected function buildConsolidatePointsPrompt(Idea $idea, array $points): string
    {
        $ideaJson = json_encode($idea->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $pointsJson = json_encode(array_map(
            static fn (RelevantPoint $point): array => $point->toArray(),
            $points
        ), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a research synthesis analyst.

Given an editorial idea and a set of relevant points:
- Merge overlapping points into cleaner, non-duplicated points.
- Keep rationale concise and strategic.
- Keep relevance scores between 0 and 1.
- If points materially disagree, place those groups in "conflicts".

A conflict should include:
- rationale: why these points conflict
- points: both the non-conflict and the conflicting points

It is very important to keep the real numbers, analytics, examples, proofs... of the point in the evidences.

Idea JSON:
{$ideaJson}

Relevant points JSON:
{$pointsJson}

Return JSON only using the provided schema.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildExtractPointsJsonSchema(): array
    {
        $maxPoints = $this->getMaxPoints();

        return [
            'type' => 'object',
            'properties' => $properties = [
                'points' => [
                    'type' => 'array',
                    'minItems' => 0,
                    'maxItems' => $maxPoints,
                    'description' => 'List of extracted research points relevant to the provided idea.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'headline' => [
                                'type' => 'string',
                                'description' => 'A summary of the key insight.',
                            ],
                            'description' => [
                                'type' => 'string',
                                'description' => 'A short explanation of the insight, including important context from the source.',
                            ],
                            'evidences' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => 'Facts/quotes/snippets/numbers/analytics/benchmark/information... or observations... taken from the source content that supports or is related.',
                            ],
                            'rationale' => [
                                'type' => 'string',
                                'description' => 'A short strategic explanation describing why this point supports the provided idea.',
                            ],
                            'relevance' => [
                                'type' => 'number',
                                'minimum' => 0,
                                'maximum' => 1,
                                'description' => 'How strongly this point relates to the provided idea, where 0 is weakly related and 1 is highly relevant.',
                            ],
                        ],
                        'required' => ['headline', 'description', 'evidences', 'rationale', 'relevance'],
                        'additionalProperties' => false,
                        'description' => 'One extracted point with its explanation, supporting evidences, and relevance score.',
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
    protected function buildConsolidatePointsJsonSchema(): array
    {
        $maxPoints = $this->getMaxPoints();

        return [
            'type' => 'object',
            'properties' => $properties = [
                'points' => [
                    'type' => 'array',
                    'minItems' => 0,
                    'maxItems' => $maxPoints,
                    'items' => $this->buildRelevantPointSchema(),
                ],
                'conflicts' => [
                    'type' => 'array',
                    'minItems' => 0,
                    'maxItems' => $maxPoints,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'rationale' => ['type' => 'string'],
                            'points' => [
                                'type' => 'array',
                                'minItems' => 2,
                                'maxItems' => $maxPoints,
                                'items' => $this->buildRelevantPointSchema(),
                            ],
                        ],
                        'required' => ['rationale', 'points'],
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
    protected function buildResolveConflictedPointsJsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $properties = [
                'point' => $this->buildRelevantPointSchema(),
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRelevantPointSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'headline' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'evidences' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Facts/quotes/snippets/numbers/analytics/benchmark/information... or observations... taken from the source content that supports or is related.',
                ],
                'rationale' => ['type' => 'string'],
                'relevance' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                ],
            ],
            'required' => ['headline', 'description', 'evidences', 'rationale', 'relevance'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<int, mixed>  $facts
     * @return list<string>
     */
    protected function normalizeFacts(array $facts): array
    {
        $normalized = [];
        foreach ($facts as $fact) {
            if ($fact instanceof Fact) {
                $normalized[] = $fact->getFact();
                continue;
            }

            if (is_array($fact) && isset($fact['fact']) && is_string($fact['fact'])) {
                $line = trim($fact['fact']);
                if ($line !== '') {
                    $normalized[] = $line;
                }
                continue;
            }

            if (is_string($fact)) {
                $line = trim($fact);
                if ($line !== '') {
                    $normalized[] = $line;
                }
            }
        }

        return array_values($normalized);
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
