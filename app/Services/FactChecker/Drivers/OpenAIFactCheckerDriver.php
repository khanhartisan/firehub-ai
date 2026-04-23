<?php

namespace App\Services\FactChecker\Drivers;

use App\Contracts\CommonData\Conflict;
use App\Contracts\CommonData\Fact;
use App\Contracts\CommonData\SemanticContext;
use App\Contracts\CommonData\Verification;
use App\Contracts\FactChecker\FactCheckable;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\Serializable;
use App\Services\FactChecker\FactCheckerService;
use RuntimeException;

class OpenAIFactCheckerDriver extends FactCheckerService
{
    protected OpenAIClient $openAIClient;

    protected string $defaultModel;

    public function __construct(OpenAIClient $openAIClient, array $config = [])
    {
        parent::__construct($config);

        $this->openAIClient = $openAIClient;
        $this->defaultModel = $config['model'] ?? 'gpt-4o-mini';
    }

    public function verify(FactCheckable $factCheckable, ?SemanticContext $context = null): Verification
    {
        $prompt = $this->buildVerificationPrompt($factCheckable, $context);

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->defaultModel)
            ->temperature(0)
            ->responseFormat([
                'type' => 'json_schema',
                'name' => 'fact_checker_verification',
                'schema' => $this->buildJsonSchema(),
                'strict' => true,
            ]);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to verify point with OpenAI: {$e->getMessage()}",
                0,
                $e
            );
        }

        $this->checkForRefusal($response);

        $responseText = $response->getFirstOutputText();
        if ($responseText === null || $responseText === '') {
            throw new RuntimeException('OpenAI returned empty fact-check response');
        }

        return $this->parseVerificationResponse($responseText);
    }

    /**
     * @return Fact[]
     */
    public function resolveConflict(Conflict $conflict): array
    {
        $prompt = $this->buildConflictResolutionPrompt($conflict);

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->defaultModel)
            ->temperature(0)
            ->responseFormat([
                'type' => 'json_schema',
                'name' => 'fact_checker_conflict_resolution',
                'schema' => $this->buildConflictResolutionJsonSchema(),
                'strict' => true,
            ]);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to resolve conflict with OpenAI: {$e->getMessage()}",
                0,
                $e
            );
        }

        $this->checkForRefusal($response);

        $responseText = $response->getFirstOutputText();
        if ($responseText === null || $responseText === '') {
            throw new RuntimeException('OpenAI returned empty conflict resolution response');
        }

        return $this->parseConflictResolutionResponse($responseText);
    }

    protected function buildVerificationPrompt(FactCheckable $factCheckable, ?SemanticContext $context = null): string
    {
        $factPayload = $factCheckable instanceof Serializable
            ? (json_encode($factCheckable->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}')
            : 'null';
        $factClaim = $factCheckable->getFactClaim();
        $contextPayload = $context
            ? (json_encode($context->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}')
            : 'null';

        return <<<PROMPT
You are a factual verification assistant.

Assess whether the provided fact claim is supported by its evidence/details and optional semantic context.

Rules:
- "is_valid" should be true only when the claim is sufficiently supported by the evidences.
- "confidence" must be a number between 0.00 and 1.00
- "reasoning" should be concise and specific (1-3 sentences).
- Do not invent external facts. Base your judgment on the provided payload only.

Fact claim:
{$factClaim}

Fact payload:
{$factPayload}

Semantic context payload:
{$contextPayload}
PROMPT;
    }

    protected function buildConflictResolutionPrompt(Conflict $conflict): string
    {
        $conflictPayload = json_encode(
            $conflict->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '{}';

        return <<<PROMPT
You are a factual conflict resolution assistant.

Given a conflict payload with multiple candidate facts and rationale, return a normalized list of facts.

Rules:
- Return only facts that are supported by the provided conflict payload.
- Merge duplicate claims that differ only by numeric figure (for example percentages) into a single resolved fact.
- When duplicate claims conflict on a figure, choose the most defensible figure using the provided rationale/context and explain why in verification.reasoning.
- Each fact must include:
  - "fact": non-empty concise statement.
  - "verification": object with "is_valid", "confidence" (0.00-1.00), and "reasoning" (1-3 sentences).
- Do not invent external facts.

Conflict payload:
{$conflictPayload}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildJsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'is_valid' => [
                    'type' => ['boolean', 'null'],
                    'description' => 'Whether the point is supported by evidence',
                ],
                'confidence' => [
                    'type' => ['number', 'null'],
                    'description' => 'Confidence score between 0.00 and 1.00',
                ],
                'reasoning' => [
                    'type' => ['string', 'null'],
                    'description' => 'Concise rationale for the decision',
                ],
            ],
            'required' => ['is_valid', 'confidence', 'reasoning'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildConflictResolutionJsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'facts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'fact' => [
                                'type' => 'string',
                                'description' => 'Resolved fact statement',
                            ],
                            'verification' => [
                                'type' => 'object',
                                'properties' => [
                                    'is_valid' => [
                                        'type' => ['boolean', 'null'],
                                    ],
                                    'confidence' => [
                                        'type' => ['number', 'null'],
                                    ],
                                    'reasoning' => [
                                        'type' => ['string', 'null'],
                                    ],
                                ],
                                'required' => ['is_valid', 'confidence', 'reasoning'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'required' => ['fact', 'verification'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['facts'],
            'additionalProperties' => false,
        ];
    }

    protected function parseVerificationResponse(string $responseText): Verification
    {
        $data = json_decode($responseText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Failed to parse fact-check response as JSON: '.json_last_error_msg()
            );
        }

        if (! is_array($data)) {
            throw new RuntimeException('Fact-check response JSON did not decode to an array');
        }

        return Verification::fromArray($data);
    }

    /**
     * @return Fact[]
     */
    protected function parseConflictResolutionResponse(string $responseText): array
    {
        $data = json_decode($responseText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Failed to parse conflict resolution response as JSON: '.json_last_error_msg()
            );
        }

        if (! is_array($data) || ! isset($data['facts']) || ! is_array($data['facts'])) {
            throw new RuntimeException('Conflict resolution response JSON did not contain a valid facts array');
        }

        return array_values(array_map(
            static fn (array $factData): Fact => Fact::fromArray($factData),
            $data['facts']
        ));
    }

    protected function checkForRefusal($response): void
    {
        foreach ($response->getOutput() as $item) {
            if (($item['type'] ?? null) !== 'message' || ! isset($item['content']) || ! is_array($item['content'])) {
                continue;
            }

            foreach ($item['content'] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    $refusalMessage = $content['refusal'] ?? 'The model refused to verify this point.';

                    throw new RuntimeException("OpenAI refused the fact-check request: {$refusalMessage}");
                }
            }
        }
    }
}
