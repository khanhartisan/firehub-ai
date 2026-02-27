<?php

namespace App\Services\ScrapePolicyEngine\Drivers;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\ScrapePolicyEngine\PolicyResult;
use App\Models\Entity;
use App\Services\ScrapePolicyEngine\ScrapePolicyEngineService;
use Carbon\Carbon;
use RuntimeException;

class OpenAIScrapePolicyEngineDriver extends ScrapePolicyEngineService
{
    protected OpenAIClient $openAIClient;

    protected string $defaultModel;

    public function __construct(OpenAIClient $openAIClient, array $config = [])
    {
        parent::__construct($config);

        $this->openAIClient = $openAIClient;
        $this->defaultModel = $config['model'] ?? 'gpt-4o-mini';
    }

    /**
     * Perform policy evaluation using OpenAI with JSON schema structured output.
     */
    protected function performEvaluation(Entity $entity, Carbon $baseTime): PolicyResult
    {
        // Calculate factors once and reuse for both the prompt and the result
        $changeBoost = $this->calculateChangeBoost($entity);
        $costFactor = $this->calculateCostFactor($entity);
        $errorPenalty = $this->calculateErrorPenalty($entity);

        $prompt = $this->buildEvaluationPrompt($entity, $baseTime, $changeBoost, $costFactor, $errorPenalty);
        $jsonSchema = $this->buildJsonSchema();

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->defaultModel)
            ->responseFormat([
                'type' => 'json_schema',
                'name' => 'scrape_policy_evaluation',
                'schema' => $jsonSchema,
                'strict' => true,
            ]);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to evaluate scraping policy with OpenAI: {$e->getMessage()}",
                0,
                $e
            );
        }

        // Check for refusals
        $this->checkForRefusal($response);

        $responseText = $response->getFirstOutputText();

        if (empty($responseText)) {
            throw new RuntimeException(
                'OpenAI returned empty policy evaluation response'
            );
        }

        $result = $this->parseResponse($responseText, $baseTime, $entity);

        // Set calculated factors (already computed above for the prompt)
        $result->setChangeBoost($changeBoost);
        $result->setCostFactor($costFactor);
        $result->setErrorPenalty($errorPenalty);

        return $result;
    }

    /**
     * Build the evaluation prompt for OpenAI.
     *
     * @param  float  $changeBoost  Pre-calculated change boost (from snapshot data)
     * @param  float  $costFactor  Pre-calculated cost factor (from snapshot data)
     * @param  float  $errorPenalty  Pre-calculated error penalty (from snapshot data)
     */
    protected function buildEvaluationPrompt(Entity $entity, Carbon $baseTime, float $changeBoost, float $costFactor, float $errorPenalty): string
    {
        // Load only the relationships we need, avoiding loading all snapshots
        $entity->loadMissing(['currentSnapshot', 'source']);

        // Get snapshot count without loading all snapshots
        $snapshotCount = $entity->snapshots()->count();

        // Query only the recent snapshots we need (last 5) for metrics calculation
        $recentSnapshots = $entity->snapshots()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $currentSnapshot = $entity->currentSnapshot;

        // Calculate metrics from recent snapshots
        $avgChangePercentage = $recentSnapshots->whereNotNull('content_change_percentage')
            ->avg('content_change_percentage') ?? 0.0;
        $avgCost = $recentSnapshots->whereNotNull('cost')->avg('cost') ?? 0.0;
        $lastScrapeAt = $entity->fetched_at;
        $daysSinceLastScrape = $lastScrapeAt ? $baseTime->diffInDays($lastScrapeAt) : null;

        // Build entity context
        $entityContext = [
            'URL' => $entity->url,
            'Type' => $entity->type?->value ?? 'unclassified',
            'Page Type' => $entity->page_type?->value ?? 'unknown',
            'Content Type' => $entity->content_type?->value ?? 'unknown',
            'Temporal Nature' => $entity->temporal?->value ?? 'unknown',
            'Scraping Status' => $entity->scraping_status?->value ?? 'pending',
            'Total Snapshots' => $snapshotCount,
            'Last Scraped' => $lastScrapeAt?->toIso8601String() ?? 'never',
            'Days Since Last Scrape' => $daysSinceLastScrape ?? 'N/A',
            'Source Published At' => $entity->source_published_at?->toIso8601String() ?? 'unknown',
            'Source Updated At' => $entity->source_updated_at?->toIso8601String() ?? 'unknown',
            'Source Authority Score' => $entity->source?->authority_score ?? 0,
            'Source Priority' => round($entity->source?->priority ?? 0.5, 2),
        ];

        if ($currentSnapshot) {
            $entityContext['Current Snapshot'] = [
                'Content Length' => $currentSnapshot->content_length ?? 'unknown',
                'Structured Data Count' => $currentSnapshot->structured_data_count ?? 0,
                'Media Count' => $currentSnapshot->media_count ?? 0,
                'Link Count' => $currentSnapshot->link_count ?? 0,
                'Cost' => $currentSnapshot->cost ?? 0.0,
            ];
        }

        $entityContext['Recent Snapshot Metrics'] = [
            'Average Change Percentage' => round($avgChangePercentage, 2),
            'Average Cost' => round($avgCost, 2),
        ];

        // Add calculated factors with explanations
        $entityContext['Calculated Factors (from historical snapshot data)'] = [
            'change_boost' => [
                'value' => round($changeBoost, 2),
                'explanation' => 'How frequently this content changes based on historical content_change_percentage. Higher values (0.7-1.0) indicate frequently changing content, lower values (0.0-0.4) indicate static content.',
            ],
            'cost_factor' => [
                'value' => round($costFactor, 2),
                'explanation' => 'Relative cost/effort to scrape based on historical cost, content length, media count, fetch duration, and structured data. Higher values indicate more expensive operations.',
            ],
            'error_penalty' => [
                'value' => round($errorPenalty, 2),
                'explanation' => 'Historical error rate based on scraping status (FAILED, TIMEOUT, BLOCKED). Higher values indicate more problematic scraping with frequent errors.',
            ],
        ];

        $contextJson = json_encode($entityContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
You are a scraping policy engine that evaluates when and how frequently web pages should be scraped.

Analyze the following entity information and determine optimal scraping policy metrics.

Entity Information:
{$contextJson}

Guidelines for evaluation:

**Note**: The following factors have already been calculated from historical snapshot data and are provided above:
- change_boost: Based on historical content change percentages
- cost_factor: Based on historical cost, content length, media count, fetch duration, and structured data
- error_penalty: Based on historical error rates (FAILED, TIMEOUT, BLOCKED statuses)

**Source Authority Score** (0-100): Indicates the trustworthiness/importance of the source. Scale is 0 to 100—higher scores mean more authoritative, trusted, or high-value sources. Use this to weight value_boost, priority, and next_scrape_at_hours—authoritative sources may warrant higher priority and more frequent scraping.

**Source Priority** (0.0-1.0): Indicates the business priority of this source. Scale is 0.0 to 1.0—higher values mean higher business priority. This is a direct signal about how important this source is to the business, independent of authority. Use this to weight priority and urgency—high priority sources should be scraped more frequently and with higher urgency.

Use these calculated factors and the source authority score to inform your decisions for the metrics below.

1. **value_boost** (0.0-1.0): How valuable is this content?
   - High (0.7-1.0): High-traffic pages, monetizable content, critical business data
   - Medium (0.4-0.7): Important but not critical content
   - Low (0.0-0.4): Low-value or archival content
   - Consider: content type, page type, structured data presence, and Source Authority Score (higher authority = typically higher value)

2. **priority** (0.0-1.0): Overall priority for scraping this entity
   - High (0.7-1.0): Critical, high-value, frequently changing content
   - Medium (0.4-0.7): Standard priority content
   - Low (0.0-0.4): Low-priority, archival, or rarely accessed content
   - Consider: combination of the calculated change_boost, value_boost, cost_factor, error_penalty, Source Authority Score, and Source Priority
   - Higher priority when: high source priority, high authority score, high change_boost + high value_boost, despite higher cost_factor or error_penalty

3. **urgency** (0.0-1.0): How urgent is it to scrape this right now?
   - High (0.7-1.0): Breaking news, time-sensitive content, long time since last scrape, high change_boost
   - Medium (0.4-0.7): Regular updates needed, moderate time since last scrape
   - Low (0.0-0.4): Not time-sensitive, recently scraped, static content (low change_boost)
   - Consider: days since last scrape, temporal nature, content freshness needs, and the calculated change_boost

4. **next_scrape_at_hours** (0-8760): Hours from base time until next scrape
   - Breaking news: 1-6 hours
   - Daily news: 6-24 hours
   - Weekly content: 24-168 hours (1-7 days)
   - Monthly content: 168-720 hours (7-30 days)
   - Static/rarely changing: 720-8760 hours (30-365 days)
   - Consider: change_boost, urgency, temporal nature, historical patterns

Base Time: {$baseTime->toIso8601String()}

Evaluate the entity and return the policy metrics according to the schema.
PROMPT;
    }

    /**
     * Build the JSON schema for structured output.
     *
     * @return array<string, mixed>
     */
    protected function buildJsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $properties = [
                'value_boost' => [
                    'type' => 'number',
                    'description' => 'How valuable is this content (0.0-1.0)',
                    'minimum' => 0.0,
                    'maximum' => 1.0,
                ],
                'priority' => [
                    'type' => 'number',
                    'description' => 'Overall priority for scraping this entity (0.0-1.0)',
                    'minimum' => 0.0,
                    'maximum' => 1.0,
                ],
                'urgency' => [
                    'type' => 'number',
                    'description' => 'How urgent is it to scrape this right now (0.0-1.0)',
                    'minimum' => 0.0,
                    'maximum' => 1.0,
                ],
                'next_scrape_at_hours' => [
                    'type' => 'number',
                    'description' => 'Hours from base time until next scrape (0-8760)',
                    'minimum' => 0,
                    'maximum' => 8760,
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * Check for refusal in the response.
     */
    protected function checkForRefusal($response): void
    {
        foreach ($response->getOutput() as $item) {
            if (($item['type'] ?? null) === 'message' && isset($item['content'])) {
                foreach ($item['content'] as $content) {
                    if (($content['type'] ?? null) === 'refusal') {
                        $refusalMessage = $content['refusal'] ?? 'The model refused to evaluate this policy.';
                        throw new RuntimeException(
                            "OpenAI refused to evaluate the scraping policy: {$refusalMessage}"
                        );
                    }
                }
            }
        }
    }

    /**
     * Parse the policy evaluation response from OpenAI.
     * With structured outputs, the response should already be valid JSON matching our schema.
     */
    protected function parseResponse(string $responseText, Carbon $baseTime, Entity $entity): PolicyResult
    {
        $data = json_decode($responseText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Failed to parse policy evaluation response as JSON: '.json_last_error_msg()
            );
        }

        $result = new PolicyResult();

        // Set value boost
        if (isset($data['value_boost'])) {
            $result->setValueBoost((float) $data['value_boost']);
        }

        // Set priority
        if (isset($data['priority'])) {
            $result->setPriority((float) $data['priority']);
        }

        // Set urgency
        if (isset($data['urgency'])) {
            $result->setUrgency((float) $data['urgency']);
        }

        // Calculate and set next scrape time
        if (isset($data['next_scrape_at_hours'])) {
            $hours = (int) $data['next_scrape_at_hours'];
            $nextScrapeAt = $baseTime->copy()->addHours($hours);
            $result->setNextScrapeAt($nextScrapeAt);
        }

        return $result;
    }
}
