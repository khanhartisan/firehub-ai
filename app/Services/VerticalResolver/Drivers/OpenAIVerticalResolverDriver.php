<?php

namespace App\Services\VerticalResolver\Drivers;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\VerticalResolver\Vertical;
use App\Contracts\VerticalResolver\VerticalMatch;
use App\Contracts\VerticalResolver\VerticalResolver;
use App\Utils\HtmlCleaner;
use RuntimeException;

class OpenAIVerticalResolverDriver implements VerticalResolver
{
    public function __construct(
        protected OpenAIClient $openAIClient,
        protected array $config = []
    ) {
        $this->config = array_merge([
            'model' => 'gpt-4o-mini',
            'max_content_length' => 50000,
            'match_threshold' => 0.4,
        ], $config);
    }

    /**
     * @param  Vertical[]  $verticals
     * @return VerticalMatch[]
     */
    public function resolve(string $content, array $verticals): array
    {
        if ($verticals === []) {
            return [];
        }

        $content = $this->prepareContent($content);
        $identifiers = [];
        $descriptions = [];
        foreach ($verticals as $v) {
            $id = $v->getIdentifier() ?? $v->getName();
            $identifiers[] = $id;
            $descriptions[] = $id . ': ' . ($v->getDescription() ?? '');
        }
        $verticalDescriptions = implode("\n", $descriptions);

        $prompt = $this->buildResolvePrompt($content, $verticalDescriptions, $identifiers);
        $jsonSchema = $this->buildResolveJsonSchema($identifiers);

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->config['model'] ?? 'gpt-4o-mini')
            ->responseFormat([
                'type' => 'json_schema',
                'name' => 'vertical_resolution',
                'schema' => $jsonSchema,
                'strict' => true,
            ]);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to resolve verticals with OpenAI: {$e->getMessage()}",
                0,
                $e
            );
        }

        $this->checkForRefusal($response);

        $responseText = $response->getFirstOutputText();

        if (empty($responseText)) {
            throw new RuntimeException(
                'OpenAI returned empty vertical resolution response'
            );
        }

        return $this->parseResolveResponse($responseText);
    }

    /**
     * @param  Vertical[]  $verticals
     * @return Vertical[]
     */
    public function propose(string $content, array $verticals): array
    {
        $content = $this->prepareContent($content);

        $prompt = $this->buildProposePrompt($content);
        $jsonSchema = $this->buildProposeJsonSchema();

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->config['model'] ?? 'gpt-4o-mini')
            ->responseFormat([
                'type' => 'json_schema',
                'name' => 'vertical_proposals',
                'schema' => $jsonSchema,
                'strict' => true,
            ]);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to propose verticals with OpenAI: {$e->getMessage()}",
                0,
                $e
            );
        }

        $this->checkForRefusal($response);

        $responseText = $response->getFirstOutputText();

        if (empty($responseText)) {
            throw new RuntimeException(
                'OpenAI returned empty vertical proposal response'
            );
        }

        return $this->parseProposeResponse($responseText);
    }

    protected function prepareContent(string $content): string
    {
        $maxLength = (int) ($this->config['max_content_length'] ?? 50000);

        if (strip_tags($content) !== $content) {
            $content = HtmlCleaner::clean($content, $maxLength);
            $content = strip_tags($content);
        }

        $content = preg_replace('/\s+/', ' ', trim($content));

        if (strlen($content) > $maxLength) {
            $content = substr($content, 0, $maxLength);
        }

        return $content;
    }

    protected function buildResolvePrompt(string $content, string $verticalDescriptions, array $identifiers): string
    {
        $namesList = implode(', ', $identifiers);

        return <<<PROMPT
You are classifying content into business verticals. Given the content below and the list of allowed verticals, determine which vertical(s) best apply.

Allowed verticals (use ONLY these exact identifiers in your response):
{$verticalDescriptions}

Rules:
- "matches": array of objects with vertical_identifier (from the list above) and confidence (0-1). Include only verticals that clearly apply (confidence >= 0.5).
- Only include verticals from this exact list: {$namesList}
- If no vertical fits, return an empty matches array.

Content to classify:
{$content}
PROMPT;
    }

    /**
     * @param  array<int, string>  $identifiers
     * @return array<string, mixed>
     */
    protected function buildResolveJsonSchema(array $identifiers): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'matches' => [
                    'type' => 'array',
                    'description' => 'Verticals that clearly apply (confidence >= 0.5)',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'vertical_identifier' => [
                                'type' => 'string',
                                'description' => 'Vertical identifier from the allowed list',
                                'enum' => $identifiers,
                            ],
                            'confidence' => [
                                'type' => 'number',
                                'description' => 'Confidence score between 0 and 1',
                            ],
                        ],
                        'required' => ['vertical_identifier', 'confidence'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['matches'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return VerticalMatch[]
     */
    protected function parseResolveResponse(string $responseText): array
    {
        $data = json_decode($responseText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Failed to parse vertical resolution response as JSON: ' . json_last_error_msg()
            );
        }

        $matchThreshold = (float) ($this->config['match_threshold'] ?? 0.4);
        $matches = [];

        foreach ($data['matches'] ?? [] as $item) {
            $confidence = (float) ($item['confidence'] ?? 0);
            if ($confidence >= $matchThreshold) {
                $matches[] = new VerticalMatch(
                    $item['vertical_identifier'] ?? '',
                    $confidence
                );
            }
        }

        usort($matches, fn (VerticalMatch $a, VerticalMatch $b) => $b->getConfidence() <=> $a->getConfidence());

        return $matches;
    }

    protected function buildProposePrompt(string $content): string
    {
        return <<<PROMPT
Based on the following content, suggest 0 to 5 new business vertical (category) names that could be used to classify similar content. Each proposal should have a short name and a description (the description can be empty if not needed).

Return a "proposals" array of objects with "name" (string) and "description" (string, optional). Use concise, lowercase names (e.g. "tech_news", "product_docs"). Do not suggest verticals that are too generic (e.g. "other", "misc").

Content:
{$content}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildProposeJsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'proposals' => [
                    'type' => 'array',
                    'description' => 'Suggested new verticals',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'Short vertical name',
                            ],
                            'description' => [
                                'type' => 'string',
                                'description' => 'Vertical description (may be empty)',
                            ],
                        ],
                        'required' => ['name', 'description'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['proposals'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return Vertical[]
     */
    protected function parseProposeResponse(string $responseText): array
    {
        $data = json_decode($responseText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Failed to parse vertical proposal response as JSON: ' . json_last_error_msg()
            );
        }

        $result = [];
        foreach ($data['proposals'] ?? [] as $item) {
            $name = $item['name'] ?? '';
            if ($name !== '') {
                $result[] = new Vertical($name, $item['description'] ?? null);
            }
        }

        return $result;
    }

    protected function checkForRefusal($response): void
    {
        foreach ($response->getOutput() as $item) {
            if (($item['type'] ?? null) === 'message' && isset($item['content'])) {
                foreach ($item['content'] as $content) {
                    if (($content['type'] ?? null) === 'refusal') {
                        $refusalMessage = $content['refusal'] ?? 'The model refused to resolve verticals.';
                        throw new RuntimeException(
                            "OpenAI refused to resolve verticals: {$refusalMessage}"
                        );
                    }
                }
            }
        }
    }
}
