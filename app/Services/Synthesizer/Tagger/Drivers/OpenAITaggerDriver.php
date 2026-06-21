<?php

namespace App\Services\Synthesizer\Tagger\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;
use App\Services\Synthesizer\Tagger\TaggerService;
use RuntimeException;

class OpenAITaggerDriver extends TaggerService
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
        $this->config = array_merge(SynthesizerSubserviceConfig::settings('tagger'), $config);
    }

    public function suggestTags(
        string $content,
        ?SemanticContext $authorContext = null,
        ?SemanticContext $generalContext = null
    ): array {
        $content = trim($content);
        if ($content === '') {
            return ['untagged'];
        }

        $data = $this->requestStructuredJson(
            $this->buildPrompt($content, $authorContext, $generalContext),
            'tagger_suggest_tags',
            $this->buildSchema(),
            'Failed to suggest tags with OpenAI'
        );

        return $this->normalizeTags($data['tags'] ?? []);
    }

    protected function getModel(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-4o-mini');
    }

    protected function getTemperature(): float
    {
        return (float) ($this->config['temperature'] ?? 0.1);
    }

    protected function getMaxTags(): int
    {
        return max(1, (int) ($this->config['max_tags'] ?? 8));
    }

    protected function buildPrompt(
        string $content,
        ?SemanticContext $authorContext,
        ?SemanticContext $generalContext
    ): string {
        $payload = [
            'content' => $content,
            'author_context' => $authorContext?->toArray(),
            'general_context' => $generalContext?->toArray(),
            'max_tags' => $this->getMaxTags(),
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are an editorial tag strategist.

Given article content and optional contexts, suggest concise and reusable tags.

Rules:
- Keep tags short and lowercase.
- Prefer broad-but-meaningful taxonomy terms.
- Avoid punctuation-heavy or duplicate tags.
- Return at most "max_tags" items.
- If the content lacks clear topical signal, return a single fallback tag: "general".

Input JSON:
{$json}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $properties = [
                'tags' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => $this->getMaxTags(),
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  mixed  $rawTags
     * @return list<string>
     */
    protected function normalizeTags(mixed $rawTags): array
    {
        if (! is_array($rawTags)) {
            return ['general'];
        }

        $normalized = [];
        foreach ($rawTags as $rawTag) {
            $tag = trim(strtolower((string) $rawTag));
            $tag = preg_replace('/\s+/', ' ', $tag);
            if (! is_string($tag) || $tag === '') {
                continue;
            }
            $normalized[] = $tag;
        }

        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) {
            return ['general'];
        }

        return array_slice($normalized, 0, $this->getMaxTags());
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
        if (! $this->openAIClient instanceof OpenAIClient) {
            throw new RuntimeException("{$failureMessage}: OpenAI client is not configured.");
        }

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
