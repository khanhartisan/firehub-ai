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
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\IdeaAuditorService;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Support\IdeaUniquenessFromVector;
use JsonException;
use RuntimeException;

/**
 * Uniqueness: retrieve candidate articles via vector search, then OpenAI structured output
 * for similarity score and redundancy judgment. Audit: OpenAI only.
 */
class OpenAIIdeaAuditorDriver extends IdeaAuditorService
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
        $this->config = array_merge(SynthesizerSubserviceConfig::settings('idea_auditor'), $config);
    }

    /**
     * @throws JsonException
     */
    public function isIdeaUnique(string $clientId, Idea $idea): IdeaUniquenessReport
    {
        $identifier = trim((string) $idea->getIdentifier());

        $text = IdeaUniquenessFromVector::searchTextForIdea($idea);
        if (trim($text) === '') {
            return (new IdeaUniquenessReport)
                ->setClientId($clientId)
                ->setIdeaIdentifier($identifier)
                ->setSimilarity(0.0)
                ->setIsUnique(true)
                ->setSimilarArticles([]);
        }

        $limit = max(1, min(100, (int) (config('synthesizer.idea_auditor.uniqueness.vector_search_limit') ?? 20)));
        $matches = IdeaUniquenessFromVector::candidateArticlesWithSimilarityScores($text, $limit);

        if ($matches === []) {
            return (new IdeaUniquenessReport)
                ->setClientId($clientId)
                ->setIdeaIdentifier($identifier)
                ->setSimilarity(0.0)
                ->setIsUnique(true)
                ->setSimilarArticles([]);
        }

        $candidates = [];
        foreach ($matches as $row) {
            $article = $row['article'];
            $candidates[] = [
                'id' => (string) $article->getKey(),
                'title' => (string) $article->title,
                'vector_similarity' => round($row['score'], 4),
            ];
        }

        $payload = [
            'client_id' => $clientId,
            'idea' => $idea->toArray(),
            'candidates_from_vector_search' => $candidates,
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
        $isUnique = !isset($data['is_unique']) || (bool) $data['is_unique'];

        $allowedIds = [];
        foreach ($candidates as $c) {
            $allowedIds[(string) $c['id']] = true;
        }

        $rawIds = $data['similar_article_ids'] ?? [];
        $similarIds = [];
        if (is_array($rawIds)) {
            foreach ($rawIds as $id) {
                $sid = (string) $id;
                if ($sid !== '' && isset($allowedIds[$sid])) {
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
            ->setIdeaIdentifier($identifier)
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
     *
     * @throws JsonException
     */
    protected function buildUniquenessPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
The field "candidates_from_vector_search" lists existing articles for this client that are nearest neighbors in embedding space to the proposed idea (each row includes a retrieval "vector_similarity" score in [0,1]). That score is only a coarse signal from vector search.

Your task: judge how editorially overlapping or redundant the proposed idea would be against those candidates for the same audience (0 = distinct enough to publish, 1 = essentially duplicate angle). Set "similarity" to your best estimate of maximum overlap with any existing piece. Set "is_unique" to true if the idea is sufficiently distinct to publish without meaningful redundancy. List only article ids that are truly overlapping in "similar_article_ids" (subset of candidate ids); use [] if none.

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
                    'description' => 'Estimated maximum editorial overlap with any existing article (your judgment, not the raw vector score).',
                ],
                'is_unique' => [
                    'type' => 'boolean',
                    'description' => 'True if the idea is sufficiently distinct to publish without redundancy.',
                ],
                'similar_article_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Candidate article ids that meaningfully overlap; empty if none.',
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
