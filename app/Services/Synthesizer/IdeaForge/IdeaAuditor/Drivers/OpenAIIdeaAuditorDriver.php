<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\IdeaForge\IdeaUniquenessReport;
use App\Models\Article;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\IdeaAuditorService;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Uses the OpenAI Responses API with structured JSON for uniqueness vs existing titles
 * and qualitative audit scores for {@see Idea}.
 */
class OpenAIIdeaAuditorDriver extends IdeaAuditorService
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
        $this->config = array_merge(config('synthesizer.openai_idea_auditor', []), $config);
    }

    public function isIdeaUnique(string $clientId, Idea $idea): IdeaUniquenessReport
    {
        $articles = $this->baselineArticles($clientId);

        if ($articles->isEmpty()) {
            return (new IdeaUniquenessReport)
                ->setClientId($clientId)
                ->setIdeaIdentifier(trim((string) $idea->getIdentifier()))
                ->setSimilarity(0.0)
                ->setIsUnique(true)
                ->setSimilarArticles([]);
        }

        $payload = [
            'client_id' => $clientId,
            'idea' => $idea->toArray(),
            'existing_articles' => $articles->map(static fn (Article $a) => [
                'id' => (string) $a->getKey(),
                'title' => (string) $a->title,
            ])->values()->all(),
        ];

        $prompt = $this->buildUniquenessPrompt($payload);
        $data = $this->requestStructuredJson(
            $prompt,
            'idea_uniqueness',
            $this->buildUniquenessJsonSchema(),
            'Failed to evaluate idea uniqueness with OpenAI',
            $this->getTemperatureUniqueness(),
        );

        $similarity = isset($data['similarity']) ? max(0.0, min(1.0, (float) $data['similarity'])) : 0.0;
        $isUnique = isset($data['is_unique']) ? (bool) $data['is_unique'] : true;

        $allowedIds = $articles->pluck('id')->map(static fn ($id) => (string) $id)->all();
        $rawIds = $data['similar_article_ids'] ?? [];
        $similarIds = [];
        if (is_array($rawIds)) {
            foreach ($rawIds as $id) {
                $sid = (string) $id;
                if ($sid !== '' && in_array($sid, $allowedIds, true)) {
                    $similarIds[] = $sid;
                }
            }
        }

        $similarIds = array_values(array_unique($similarIds));

        $similarArticles = $similarIds === []
            ? []
            : Article::query()
                ->where('client_id', $clientId)
                ->whereIn('id', $similarIds)
                ->get()
                ->all();

        return (new IdeaUniquenessReport)
            ->setClientId($clientId)
            ->setIdeaIdentifier(trim((string) $idea->getIdentifier()))
            ->setSimilarity($similarity)
            ->setIsUnique($isUnique)
            ->setSimilarArticles($similarArticles);
    }

    public function audit(Idea $idea): IdeaAuditReport
    {
        $prompt = $this->buildAuditPrompt($idea);
        $data = $this->requestStructuredJson(
            $prompt,
            'idea_audit',
            $this->buildAuditJsonSchema(),
            'Failed to audit idea with OpenAI',
            $this->getTemperatureAudit(),
        );

        $score = isset($data['score']) ? max(0.0, min(1.0, (float) $data['score'])) : null;

        $highlights = [];
        if (isset($data['highlights']) && is_array($data['highlights'])) {
            foreach ($data['highlights'] as $line) {
                $highlights[] = (string) $line;
            }
        }

        $concerns = [];
        if (isset($data['concerns']) && is_array($data['concerns'])) {
            foreach ($data['concerns'] as $line) {
                $concerns[] = (string) $line;
            }
        }

        if ($highlights === []) {
            throw new RuntimeException('OpenAI audit response must include at least one highlight.');
        }

        return new IdeaAuditReport($idea, $score, $highlights, $concerns);
    }

    /**
     * @return Collection<int, Article>
     */
    protected function baselineArticles(string $clientId): Collection
    {
        return Article::query()
            ->where('client_id', $clientId)
            ->select(['id', 'title'])
            ->limit($this->getMaxBaselineArticles())
            ->get();
    }

    protected function getMaxBaselineArticles(): int
    {
        return max(1, min(50, (int) ($this->config['max_baseline_articles'] ?? 20)));
    }

    protected function getModel(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-4o-mini');
    }

    protected function getTemperatureUniqueness(): float
    {
        return (float) ($this->config['temperature_uniqueness'] ?? 0.1);
    }

    protected function getTemperatureAudit(): float
    {
        return (float) ($this->config['temperature_audit'] ?? 0.3);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function buildUniquenessPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You compare a proposed article idea against existing published/working titles for the same client.

Estimate how overlapping or redundant the new idea would be with any existing title (0 = completely distinct, 1 = essentially the same topic/angle).

Only include article ids in "similar_article_ids" when the existing piece is meaningfully overlapping (would feel repetitive to publish both). Use an empty array when none are close.

Input JSON:
{$json}
PROMPT;
    }

    protected function buildAuditPrompt(Idea $idea): string
    {
        $json = json_encode($idea->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are an editorial quality reviewer. Score the article idea below for clarity, audience fit, and publishability (0 = weak, 1 = strong).

List concise strengths in "highlights" and concrete risks or gaps in "concerns" (concerns may be empty if none).

Idea JSON:
{$json}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildUniquenessJsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $properties = [
                'similarity' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                    'description' => 'Maximum conceptual overlap with any existing title.',
                ],
                'is_unique' => [
                    'type' => 'boolean',
                    'description' => 'True if the idea is sufficiently distinct to publish without redundancy.',
                ],
                'similar_article_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Subset of input existing_articles ids that overlap; empty if none.',
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildAuditJsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $properties = [
                'score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                ],
                'highlights' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 8,
                    'items' => ['type' => 'string'],
                ],
                'concerns' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
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
        float $temperature,
    ): array {
        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->getModel())
            ->temperature($temperature)
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
