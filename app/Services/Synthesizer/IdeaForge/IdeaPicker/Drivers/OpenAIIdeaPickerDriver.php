<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\IdeaPickerService;
use RuntimeException;

/**
 * Uses the OpenAI Responses API with structured JSON to choose the best
 * {@see IdeaAuditReport} rows for a given context (best-first).
 */
class OpenAIIdeaPickerDriver extends IdeaPickerService
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
        $this->config = array_merge(SynthesizerSubserviceConfig::settings('idea_picker'), $config);
    }

    /**
     * @param IdeaAuditReport[] $ideaAuditReports
     * @return IdeaAuditReport[]|null
     * @throws \JsonException
     */
    public function pick(array $ideaAuditReports, SemanticContext $context, int $limit = 1): ?array
    {
        $reports = array_values(array_filter(
            $ideaAuditReports,
            static fn ($report): bool => $report instanceof IdeaAuditReport
        ));

        if ($reports === []) {
            return null;
        }

        $limit = max(1, $limit);

        if (count($reports) === 1) {
            return [$reports[0]];
        }

        $candidates = [];
        foreach ($reports as $i => $report) {
            $id = $report->getIdentifier();
            $candidates[] = [
                'index' => $i,
                'audit_report_identifier' => $id !== null && $id !== '' ? $id : null,
                'score' => $report->getScore(),
                'highlights' => $report->getHighlights(),
                'concerns' => $report->getConcerns(),
                'idea' => $report->getIdea()->toArray(),
            ];
        }

        $payload = [
            'context' => $context->toArray(),
            'pick_at_most' => min($limit, count($reports)),
            'candidates' => $candidates,
        ];

        $prompt = $this->buildPickPrompt($payload);
        $data = $this->requestStructuredJson(
            $prompt,
            'idea_pick',
            $this->buildPickJsonSchema(min($limit, count($reports))),
            'Failed to pick ideas with OpenAI',
            $this->getTemperaturePick(),
        );

        $rawIds = $data['picked_audit_report_identifiers'] ?? [];
        if (! is_array($rawIds)) {
            $rawIds = [];
        }

        $picked = $this->resolvePickedReports($reports, $rawIds, $limit);

        if ($picked === []) {
            $picked = $this->fallbackPickByScore($reports, $limit);
        }

        return $picked === [] ? null : $picked;
    }

    /**
     * @param  IdeaAuditReport[]  $reports
     * @param  mixed[]  $rawIds
     * @return IdeaAuditReport[]
     */
    protected function resolvePickedReports(array $reports, array $rawIds, int $limit): array
    {
        $byId = [];
        foreach ($reports as $report) {
            $id = $report->getIdentifier();
            if ($id !== null && $id !== '') {
                $byId[$id] = $report;
            }
        }

        $out = [];
        $seen = [];
        foreach ($rawIds as $raw) {
            $sid = (string) $raw;
            if ($sid === '' || isset($seen[$sid])) {
                continue;
            }
            if (! isset($byId[$sid])) {
                continue;
            }
            $seen[$sid] = true;
            $out[] = $byId[$sid];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  IdeaAuditReport[]  $reports
     * @return IdeaAuditReport[]
     */
    protected function fallbackPickByScore(array $reports, int $limit): array
    {
        usort(
            $reports,
            static fn (IdeaAuditReport $left, IdeaAuditReport $right): int => ($right->getScore() ?? 0) <=> ($left->getScore() ?? 0)
        );

        return array_slice($reports, 0, $limit);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function buildPickPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are choosing which audited article ideas best fit the editorial context. Each candidate includes audit score, highlights, concerns, and the full idea payload.

Return "picked_audit_report_identifiers" as a list of audit_report_identifier values from the input candidates only, best match first. Include at most the number given by pick_at_most. If nothing is suitable, use an empty list.

You're not restricted to rely on the audit scores. You pick the ideas that fit the best with the current client context, that is balanced between:
- Continuity Ideas: Topics that logically advance the recent content and match the "Audience Knowledge Stage".
- Exploration Ideas: Fresh, tangential, or entirely new angles that diversify the website's content while still strictly respecting the overarching "Editorial / business context".

Your duty is to make sure that the new idea will bring the better engagement and best experience for the reader of the client's context. 

For example an idea may have higher audit score because it's more relevant and more continuity, but you may choose the 2nd idea that brings a fresh air to the site because the current contents have been continued for too long, and the 2nd idea supports to expand the current website contents...

Input JSON:
{$json}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPickJsonSchema(int $maxPicks): array
    {
        $maxItems = max(1, $maxPicks);

        return [
            'type' => 'object',
            'properties' => $properties = [
                'picked_audit_report_identifiers' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'maxItems' => $maxItems,
                    'description' => 'Ordered list of audit_report_identifier values chosen from the input; best first.',
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    protected function getModel(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-4o-mini');
    }

    protected function getTemperaturePick(): float
    {
        return (float) ($this->config['temperature'] ?? 0.2);
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
