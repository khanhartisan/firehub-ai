<?php

namespace App\Services\Synthesizer\Researcher\Drivers;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\Researcher\IdeaPoint;
use App\Contracts\Synthesizer\Researcher\IdeaPoints;
use App\Contracts\Synthesizer\Researcher\Point;
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

    public function extractPoints(Idea $idea, string $content): IdeaPoints
    {
        $content = trim($content);
        if ($content === '') {
            return new IdeaPoints($idea);
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
            return new IdeaPoints($idea);
        }

        $ideaPoints = [];
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

            $relevance = isset($row['relevance']) ? max(0.0, min(1.0, (float) $row['relevance'])) : null;

            $ideaPoints[] = new IdeaPoint(
                $idea,
                (new Point)
                    ->setHeadline($headline)
                    ->setDescription($description)
                    ->setEvidences($evidences),
                $relevance
            );
        }

        return new IdeaPoints($idea, $ideaPoints);
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
        return max(1, min(20, (int) ($this->config['max_points'] ?? 8)));
    }

    protected function buildExtractPointsPrompt(Idea $idea, string $content): string
    {
        $ideaJson = json_encode($idea->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a research analyst for editorial planning. Extract concise, high-signal evidence-based points from the source content that support or challenge the provided idea.

Each point should include:
- headline: short and punchy
- description: concise explanation
- evidences: concrete facts/quotes/snippets from source content
- relevance: 0..1 relevance to the idea

Idea JSON:
{$ideaJson}

Source content:
{$content}

Return JSON only (via schema), ordered by relevance descending.
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
                    'minItems' => 1,
                    'maxItems' => $maxPoints,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'headline' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'evidences' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'relevance' => [
                                'type' => 'number',
                                'minimum' => 0,
                                'maximum' => 1,
                            ],
                        ],
                        'required' => ['headline', 'description', 'evidences', 'relevance'],
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
