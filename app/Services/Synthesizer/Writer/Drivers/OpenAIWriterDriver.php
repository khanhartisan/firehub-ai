<?php

namespace App\Services\Synthesizer\Writer\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
use App\Contracts\DOM\Element;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\Synthesizer\Writer\Draft;
use App\Contracts\Synthesizer\Writer\IllustrationAnchor;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;
use App\Services\Synthesizer\Writer\WriterService;
use League\CommonMark\Exception\CommonMarkException;
use RuntimeException;

class OpenAIWriterDriver extends WriterService
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
        $this->config = array_merge(SynthesizerSubserviceConfig::settings('writer'), $config);
    }

    public function draft(
        Brief $brief,
        Outline $outline,
        ?SemanticContext $authorContext = null,
        ?SemanticContext $generalContext = null,
    ): Draft
    {
        if (! $this->openAIClient instanceof OpenAIClient) {
            throw new RuntimeException('OpenAI author driver requires an OpenAI client instance.');
        }

        $payload = $this->generateDraftPayload($brief, $outline, $authorContext, $generalContext);

        $article = $this->buildArticleFromPayload($payload);
        if ($article->getChildren() === []) {
            throw new RuntimeException('OpenAI author driver returned an empty article.');
        }

        return (new Draft)
            ->setTitle($this->sanitizeNullableString($payload['title'] ?? null) ?: $brief->getTitle())
            ->setExcerpt($this->sanitizeNullableString($payload['excerpt'] ?? null) ?: $brief->getDescription())
            ->setArticle($article);
    }

    /**
     * @param  IllustrationResult[]  $illustrationResults
     * @return IllustrationAnchor[]
     */
    public function getIllustrationAnchors(Article $article, array $illustrationResults): array
    {
        $results = array_values(array_filter(
            $illustrationResults,
            static fn (mixed $item): bool => $item instanceof IllustrationResult
        ));

        if ($results === []) {
            return [];
        }

        if (! $this->openAIClient instanceof OpenAIClient) {
            throw new RuntimeException('OpenAI author driver requires an OpenAI client instance.');
        }

        $elementIds = $this->collectElementIdentifiers($article);
        if ($elementIds === []) {
            throw new RuntimeException('Cannot resolve illustration anchors: article DOM has no identifiable elements.');
        }

        $payload = $this->generateIllustrationAnchorsPayload($article, $results, $elementIds);

        return $this->validateAndHydrateIllustrationAnchors($payload, $results, $elementIds);
    }

    /**
     * @param  IllustrationResult[]  $results
     * @param  list<string>  $elementIds
     * @return array<string, mixed>
     */
    protected function generateIllustrationAnchorsPayload(
        Article $article,
        array $results,
        array $elementIds,
    ): array {
        $count = count($results);
        $illustrationIds = array_values(array_unique(array_map(
            static fn (IllustrationResult $result): string => $result->getIdentifier(),
            $results
        )));

        if (count($illustrationIds) !== $count) {
            throw new RuntimeException('Cannot resolve illustration anchors: duplicate illustration identifiers in input.');
        }

        $data = $this->requestStructuredJson(
            $this->buildIllustrationAnchorsPrompt($article, $results, $elementIds, $count),
            'author_illustration_anchors',
            $this->buildIllustrationAnchorsSchema($count, $illustrationIds, $elementIds),
            'Failed to resolve illustration anchors with OpenAI'
        );

        return is_array($data) ? $data : [];
    }

    /**
     * @param  IllustrationResult[]  $results
     * @param  list<string>  $elementIds
     */
    protected function buildIllustrationAnchorsPrompt(
        Article $article,
        array $results,
        array $elementIds,
        int $expectedAnchorCount,
    ): string {
        $input = [
            'article' => $article->toArray(),
            'illustrations' => array_map(
                static fn (IllustrationResult $result): array => $result->toArray(),
                $results
            ),
            'allowed_element_identifiers' => $elementIds,
        ];
        $json = json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a senior editor placing illustrations in an article DOM.

Each illustration must appear exactly once in your output. Return exactly {$expectedAnchorCount} anchors in the anchors array (same length as illustrations).
For each illustration (in input array order), choose the DOM element identifier that is the best structural anchor: the illustration will be inserted immediately before or after that element according to is_after (true = after the element, false = before).

Rules:
- illustration_identifier must match one of the illustration identifiers in the input.
- element_identifier must be one of allowed_element_identifiers.
- Cover every illustration exactly once across the anchors array.

Input JSON:
{$json}
PROMPT;
    }

    /**
     * @param  list<string>  $illustrationIds
     * @param  list<string>  $elementIds
     * @return array<string, mixed>
     */
    protected function buildIllustrationAnchorsSchema(
        int $anchorCount,
        array $illustrationIds,
        array $elementIds,
    ): array {
        return [
            'type' => 'object',
            'properties' => [
                'anchors' => [
                    'type' => 'array',
                    'minItems' => $anchorCount,
                    'maxItems' => $anchorCount,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'illustration_identifier' => [
                                'type' => 'string',
                                'enum' => $illustrationIds,
                            ],
                            'element_identifier' => [
                                'type' => 'string',
                                'enum' => $elementIds,
                            ],
                            'is_after' => [
                                'type' => 'boolean',
                            ],
                        ],
                        'required' => ['illustration_identifier', 'element_identifier', 'is_after'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['anchors'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  IllustrationResult[]  $results
     * @param  list<string>  $elementIds
     * @return IllustrationAnchor[]
     */
    protected function validateAndHydrateIllustrationAnchors(
        array $payload,
        array $results,
        array $elementIds,
    ): array {
        $anchorsRaw = $payload['anchors'] ?? null;
        if (! is_array($anchorsRaw)) {
            throw new RuntimeException('Failed to resolve illustration anchors with OpenAI: missing anchors array.');
        }

        $elementIdLookup = array_fill_keys($elementIds, true);
        $built = [];

        foreach ($anchorsRaw as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('Failed to resolve illustration anchors with OpenAI: invalid anchor row.');
            }

            try {
                $built[] = IllustrationAnchor::fromArray($row);
            } catch (\InvalidArgumentException $e) {
                throw new RuntimeException(
                    'Failed to resolve illustration anchors with OpenAI: '.$e->getMessage(),
                    0,
                    $e
                );
            }
        }

        if (count($built) !== count($results)) {
            throw new RuntimeException('Failed to resolve illustration anchors with OpenAI: unexpected anchor count.');
        }

        $expectedIllustrationIds = array_map(
            static fn (IllustrationResult $result): string => $result->getIdentifier(),
            $results
        );
        $fromResponse = array_map(
            static fn (IllustrationAnchor $anchor): string => $anchor->getIllustrationIdentifier(),
            $built
        );
        sort($expectedIllustrationIds);
        sort($fromResponse);
        if ($fromResponse !== $expectedIllustrationIds) {
            throw new RuntimeException(
                'Failed to resolve illustration anchors with OpenAI: illustration identifiers do not match the requested set.'
            );
        }

        foreach ($built as $anchor) {
            if (! isset($elementIdLookup[$anchor->getElementIdentifier()])) {
                throw new RuntimeException(
                    'Failed to resolve illustration anchors with OpenAI: unknown element identifier in anchor output.'
                );
            }
        }

        return $built;
    }

    /**
     * @return list<string>
     */
    protected function collectElementIdentifiers(Element $root): array
    {
        $ids = [];

        $visit = function (Element $node) use (&$visit, &$ids): void {
            $ids[] = $node->getIdentifier();
            foreach ($node->getChildren() as $child) {
                if ($child instanceof Element) {
                    $visit($child);
                }
            }
        };

        $visit($root);

        return array_values(array_unique($ids));
    }

    protected function getModel(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-4o-mini');
    }

    protected function getTemperature(): float
    {
        return (float) ($this->config['temperature'] ?? 0.2);
    }

    /**
     * @return array<string, mixed>
     */
    protected function generateDraftPayload(
        Brief $brief,
        Outline $outline,
        ?SemanticContext $authorContext,
        ?SemanticContext $generalContext,
    ): array
    {
        $data = $this->requestStructuredJson(
            $this->buildPrompt($brief, $outline, $authorContext, $generalContext),
            'author_draft',
            $this->buildDraftSchema(),
            'Failed to build author draft with OpenAI'
        );

        return is_array($data) ? $data : [];
    }

    protected function buildPrompt(
        Brief $brief,
        Outline $outline,
        ?SemanticContext $authorContext,
        ?SemanticContext $generalContext,
    ): string
    {
        $payload = [
            'brief' => $brief->toArray(),
            'outline' => $outline->toArray(),
            'author_context' => $authorContext?->toArray(),
            'general_context' => $generalContext?->toArray(),
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a senior editorial writer.

Given a brief, outline, and optional author and general context, produce a publication-ready article draft:
- You have the freedom to express and organize the article by your way, but make sure you make use of all the points provided by the outline, as well as the related sub-points and evidences.
- Keep title and excerpt concise, specific, and publish-ready.
- Return the article body as Markdown (not HTML or JSON DOM). Use h2 and below for section headings; do not include h1 because the CMS handles the page title.
- Use standard Markdown for paragraphs, lists, links, emphasis, and code blocks as appropriate.
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
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Publication-ready article title.',
                ],
                'excerpt' => [
                    'type' => 'string',
                    'description' => 'Short article excerpt/summary.',
                ],
                'markdown' => [
                    'type' => 'string',
                    'description' => 'Article body in Markdown. Use h2+ for sections; do not include h1.',
                ],
            ],
            'required' => ['title', 'excerpt', 'markdown'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function buildArticleFromPayload(array $payload): Article
    {
        $markdown = $payload['markdown'] ?? null;
        if (! is_string($markdown) || trim($markdown) === '') {
            return new Article;
        }

        try {
            return Article::fromMarkdown($markdown);
        } catch (CommonMarkException $e) {
            throw new RuntimeException(
                'Failed to build author draft with OpenAI: invalid markdown ('.$e->getMessage().').',
                0,
                $e
            );
        }
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
