<?php

namespace App\Services\Synthesizer\Critic\Drivers;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\Synthesizer\Critic\Criticism;
use App\Services\Synthesizer\Critic\CriticManager;
use App\Services\Synthesizer\Critic\CriticService;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;
use RuntimeException;

class OpenAICriticDriver extends CriticService
{
    protected ?OpenAIClient $openAIClient;

    protected int $minCriticisms = 0;

    protected int $maxCriticisms = 10;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        CriticManager $criticManager,
        string $purpose,
        ?OpenAIClient $openAIClient = null,
        array $config = [],
    ) {
        $this->openAIClient = $openAIClient;
        parent::__construct(
            $criticManager,
            $purpose,
            array_merge(SynthesizerSubserviceConfig::settings('critic'), $config),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $allowedReferences
     * @param  array<string, true>  $rectifiedReferences
     * @return list<Criticism>
     */
    protected function criticize(
        array $payload,
        array $allowedReferences,
        array $rectifiedReferences,
    ): array {
        if (! $this->openAIClient instanceof OpenAIClient) {
            throw new RuntimeException(
                "Failed to criticize article with OpenAI ({$this->purpose}): OpenAI client is not configured."
            );
        }

        $definition = $this->criticManager->makeArticleCritic($this->purpose);
        $payload['critic_purpose'] = $this->purpose;
        $payload['critic_description'] = $definition->getDescription();

        $data = $this->requestStructuredJson(
            $definition->renderPrompt($payload),
            $definition->responseSchemaName(),
            $this->buildCriticismSchema($allowedReferences),
            "Failed to criticize article with OpenAI ({$this->purpose})",
        );

        return $this->hydrateCriticisms($data, $allowedReferences, $rectifiedReferences);
    }

    /**
     * @param  list<string>  $allowedReferences
     * @return array<string, mixed>
     */
    protected function buildCriticismSchema(array $allowedReferences): array
    {
        $referenceSchema = $allowedReferences === []
            ? ['type' => 'string']
            : ['type' => 'string', 'enum' => $allowedReferences];

        return [
            'type' => 'object',
            'properties' => $properties = [
                'criticisms' => [
                    'type' => 'array',
                    'maxItems' => $this->getMaxCriticisms(),
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'reference' => array_merge(
                                $referenceSchema,
                                ['description' => 'DOM reference identifier for the criticized section.']
                            ),
                            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                            'importance' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                            'remarks' => [
                                'type' => 'array',
                                'minItems' => 1,
                                'maxItems' => 8,
                                'items' => ['type' => 'string'],
                            ],
                        ],
                        'minItems' => $this->minCriticisms,
                        'maxItems' => $this->maxCriticisms,
                        'required' => ['reference', 'confidence', 'importance', 'remarks'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowedReferences
     * @param  array<string, true>  $rectifiedReferences
     * @return list<Criticism>
     */
    protected function hydrateCriticisms(
        array $data,
        array $allowedReferences,
        array $rectifiedReferences,
    ): array {
        $allowedLookup = array_fill_keys($allowedReferences, true);
        $rawCriticisms = $data['criticisms'] ?? [];
        if (! is_array($rawCriticisms)) {
            return [];
        }

        $criticisms = [];

        foreach ($rawCriticisms as $row) {
            if (! is_array($row)) {
                continue;
            }

            $reference = trim((string) ($row['reference'] ?? ''));
            if ($reference === '' || ! isset($allowedLookup[$reference]) || isset($rectifiedReferences[$reference])) {
                continue;
            }

            $remarks = [];
            foreach (is_array($row['remarks'] ?? null) ? $row['remarks'] : [] as $remark) {
                $text = trim((string) $remark);
                if ($text !== '') {
                    $remarks[] = $text;
                }
            }

            if ($remarks === []) {
                continue;
            }

            $criticisms[] = (new Criticism)
                ->setPurpose($this->purpose)
                ->setReference($reference)
                ->setConfidence($this->normalizeScore($row['confidence'] ?? null))
                ->setImportance($this->normalizeScore($row['importance'] ?? null))
                ->setRemarks($remarks);
        }

        return $criticisms;
    }

    protected function normalizeScore(mixed $value): ?float
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        return max(0.0, min(1.0, round((float) $value, 2)));
    }

    protected function getModel(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-5.4-mini');
    }

    protected function getTemperature(): float
    {
        return (float) ($this->config['temperature'] ?? 0.2);
    }

    protected function getMaxCriticisms(): int
    {
        return max(1, min(50, (int) ($this->config['max_criticisms_per_critic'] ?? 10)));
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
            throw new RuntimeException("{$failureMessage}: {$e->getMessage()}", 0, $e);
        }

        $this->checkForRefusal($response);

        $text = $response->getFirstOutputText();
        if ($text === null || $text === '') {
            throw new RuntimeException("{$failureMessage}: empty model output.");
        }

        $data = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            throw new RuntimeException("{$failureMessage}: invalid JSON (".json_last_error_msg().').');
        }

        return $data;
    }

    protected function checkForRefusal(Response $response): void
    {
        foreach ($response->getOutput() as $item) {
            if (($item['type'] ?? null) !== 'message' || ! isset($item['content'])) {
                continue;
            }

            foreach ($item['content'] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    $message = $content['refusal'] ?? 'The model refused to complete this request.';
                    throw new RuntimeException("OpenAI refused the request: {$message}");
                }
            }
        }
    }
}
