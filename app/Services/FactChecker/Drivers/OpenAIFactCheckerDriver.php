<?php

namespace App\Services\FactChecker\Drivers;

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
