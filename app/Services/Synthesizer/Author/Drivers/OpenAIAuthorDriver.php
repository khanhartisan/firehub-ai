<?php

namespace App\Services\Synthesizer\Author\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
use App\Contracts\DOM\ElementType;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\Synthesizer\Author\Draft;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Services\Synthesizer\Author\AuthorService;
use RuntimeException;

class OpenAIAuthorDriver extends AuthorService
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
        $this->config = array_merge(config('synthesizer.openai_author', []), $config);
    }

    public function draft(Brief $brief, Outline $outline, ?SemanticContext $context = null): Draft
    {
        if (! $this->openAIClient instanceof OpenAIClient) {
            throw new RuntimeException('OpenAI author driver requires an OpenAI client instance.');
        }

        $payload = $this->generateDraftPayload($brief, $outline, $context);

        $article = $this->buildArticleFromPayload($payload);
        if ($article->getChildren() === []) {
            throw new RuntimeException('OpenAI author driver returned an empty article.');
        }

        return (new Draft)
            ->setTitle($this->sanitizeNullableString($payload['title'] ?? null) ?: $brief->getTitle())
            ->setExcerpt($this->sanitizeNullableString($payload['excerpt'] ?? null) ?: $brief->getDescription())
            ->setArticle($article);
    }

    protected function getModel(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-4o-mini');
    }

    protected function getTemperature(): float
    {
        return (float) ($this->config['temperature'] ?? 0.2);
    }

    public function getMaxChildren(): int
    {
        return (int) ($this->config['max_children'] ?? 100);
    }

    protected function getMaxDepth(): int
    {
        return max(1, min(8, (int) ($this->config['max_depth'] ?? 8)));
    }

    /**
     * @return array<string, mixed>
     */
    protected function generateDraftPayload(Brief $brief, Outline $outline, ?SemanticContext $context): array
    {
        $data = $this->requestStructuredJson(
            $this->buildPrompt($brief, $outline, $context),
            'author_draft',
            $this->buildDraftSchema(),
            'Failed to build author draft with OpenAI'
        );

        return is_array($data) ? $data : [];
    }

    protected function buildPrompt(Brief $brief, Outline $outline, ?SemanticContext $context): string
    {
        $payload = [
            'brief' => $brief->toArray(),
            'outline' => $outline->toArray(),
            'semantic_context' => $context?->toArray(),
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a senior editorial writer.

Given a brief, outline, and optional semantic context, produce structured article draft content:
- You have the freedom to express and organize the article by your way, but make sure you make use of all the points provided by the outline, as well as the related sub-points and evidences.
- Keep title and excerpt concise, specific, and publish-ready.
- Do not include h1 in article content; h1 is handled by CMS.
- Keep tone and voice aligned with the brief.

Input JSON:
{$json}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildDraftSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $properties = [
                'title' => [
                    'type' => 'string',
                    'description' => 'Publication-ready article title.',
                ],
                'excerpt' => [
                    'type' => 'string',
                    'description' => 'Short article excerpt/summary.',
                ],
                'article' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'enum' => [ElementType::ARTICLE->value],
                        ],
                        'props' => [
                            'type' => 'object',
                            'additionalProperties' => ['type' => 'string'],
                        ],
                        'children' => [
                            'type' => 'array',
                            'maxItems' => $this->getMaxChildren(),
                            'items' => $this->buildElementOrTextSchema(1),
                        ],
                    ],
                    'required' => ['type', 'children'],
                    'additionalProperties' => false,
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function buildArticleFromPayload(array $payload): Article
    {
        $rawArticle = $payload['article'] ?? null;
        if (! is_array($rawArticle)) {
            return new Article;
        }

        return Article::fromArray($rawArticle);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildElementOrTextSchema(int $depth): array
    {
        return [
            'anyOf' => [
                ['type' => 'string'],
                $this->buildElementSchema($depth),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildElementSchema(int $depth): array
    {
        $childrenItems = $depth >= $this->getMaxDepth()
            ? ['type' => 'string']
            : $this->buildElementOrTextSchema($depth + 1);

        return [
            'type' => 'object',
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => array_values(array_map(
                        static fn (ElementType $type): string => $type->value,
                        array_filter(ElementType::cases(), static fn (ElementType $type): bool => $type !== ElementType::H1)
                    )),
                ],
                'props' => [
                    'type' => 'object',
                    'additionalProperties' => ['type' => 'string'],
                ],
                'children' => [
                    'type' => 'array',
                    'maxItems' => $this->getMaxChildren(),
                    'items' => $childrenItems,
                ],
            ],
            'required' => ['type', 'children'],
            'additionalProperties' => false,
        ];
    }

    protected function sanitizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
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
