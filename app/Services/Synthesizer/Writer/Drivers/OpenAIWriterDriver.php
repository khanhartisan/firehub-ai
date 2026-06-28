<?php

namespace App\Services\Synthesizer\Writer\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\Synthesizer\Critic\Criticism;
use App\Contracts\Synthesizer\Critic\Rectification;
use App\Contracts\Synthesizer\Writer\Draft;
use App\Contracts\Synthesizer\Writer\IllustrationAnchor;
use App\Contracts\Synthesizer\Writer\RectifiedArticle;
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
     * @param  Criticism[]  $criticisms
     */
    public function rectifyArticle(
        Article $article,
        array $criticisms,
        ?SemanticContext $authorContext = null,
        ?SemanticContext $generalContext = null,
    ): RectifiedArticle {
        $normalized = $this->normalizeCriticisms($criticisms);
        if ($normalized === []) {
            return (new RectifiedArticle)
                ->setArticle($article)
                ->setRectifications([]);
        }

        if (! $this->openAIClient instanceof OpenAIClient) {
            throw new RuntimeException('OpenAI author driver requires an OpenAI client instance.');
        }

        $elementReferences = $this->collectElementReferences($article);

        if ($this->allCriticismsHaveReference($normalized)) {
            if ($elementReferences === []) {
                throw new RuntimeException('Cannot rectify article: article DOM has no identifiable elements.');
            }

            $payload = $this->generateTargetedRectifyArticlePayload(
                $article,
                $normalized,
                $elementReferences,
                $authorContext,
                $generalContext,
            );

            return $this->hydrateTargetedRectifiedArticle($article, $payload);
        }

        $payload = $this->generateFullArticleRectifyPayload(
            $article,
            $normalized,
            $authorContext,
            $generalContext,
        );

        return $this->hydrateFullArticleRectifiedArticle($payload);
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
     * @param  list<Criticism>  $criticisms
     * @param  list<string>  $elementReferences
     * @return array<string, mixed>
     */
    protected function generateTargetedRectifyArticlePayload(
        Article $article,
        array $criticisms,
        array $elementReferences,
        ?SemanticContext $authorContext,
        ?SemanticContext $generalContext,
    ): array {
        $data = $this->requestStructuredJson(
            $this->buildTargetedRectifyArticlePrompt($article, $criticisms, $elementReferences, $authorContext, $generalContext),
            'author_rectify_article_targeted',
            $this->buildTargetedRectifyArticleSchema($elementReferences),
            'Failed to rectify article with OpenAI',
        );

        return is_array($data) ? $data : [];
    }

    /**
     * @param  list<Criticism>  $criticisms
     * @return array<string, mixed>
     */
    protected function generateFullArticleRectifyPayload(
        Article $article,
        array $criticisms,
        ?SemanticContext $authorContext,
        ?SemanticContext $generalContext,
    ): array {
        $data = $this->requestStructuredJson(
            $this->buildFullArticleRectifyPrompt($article, $criticisms, $authorContext, $generalContext),
            'author_rectify_article_full',
            $this->buildFullArticleRectifySchema(),
            'Failed to rectify article with OpenAI',
        );

        return is_array($data) ? $data : [];
    }

    /**
     * @param  list<Criticism>  $criticisms
     * @param  list<string>  $elementReferences
     */
    protected function buildTargetedRectifyArticlePrompt(
        Article $article,
        array $criticisms,
        array $elementReferences,
        ?SemanticContext $authorContext,
        ?SemanticContext $generalContext,
    ): string {
        $payload = [
            'article' => $article->toArray(),
            'element_references' => $elementReferences,
            'criticisms' => array_map(
                static fn (Criticism $criticism): array => $criticism->toArray(),
                $criticisms
            ),
            'author_context' => $authorContext?->toArray(),
            'general_context' => $generalContext?->toArray(),
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a senior editorial writer applying localized revisions to an article DOM.

Address the criticisms by choosing the DOM operations that best resolve them. You may modify any existing nodes as needed; leave unaffected nodes unchanged when possible.
Choose the DOM operation that best addresses each criticism.

Rules:
- fixes[].reference must identify a DOM node that exists when that fix runs. Fixes execute sequentially; later fixes may target nodes introduced by earlier fixes in the sequence, not only element_references from the initial article.
- fixes[].operation must be one of: remove, replace, insert_before, insert_after.
- remove: delete the node at reference from the article (omit elements).
- replace: remove the node at reference and insert fixes[].elements in its place (1+ sibling nodes). When using one element, its identifier must equal reference. When splitting into multiple nodes, the first element should reuse reference; give additional siblings new unique identifiers not used elsewhere in the article.
- insert_before: keep the node at reference; insert fixes[].elements immediately before it (new identifiers required).
- insert_after: keep the node at reference; insert fixes[].elements immediately after it (new identifiers required).
- Preserve element types when reasonable; you may change children and props as needed to address the criticisms.
- rectifications[].reference must match a fix you applied (including removals).
- rectifications[].confidence is how confident you are the fix fully addresses the related criticisms (0.00–1.00).
- adjustments must be short, specific strings describing applied fixes (e.g. "Removed redundant section").

Instructions:
- The fixes will be executed sequentially.
- In case you want to make a complex modification, for example: replacing multiple nodes by multiple nodes -> You can do that by: Create a "fix" to remove all the current nodes by their references, then create another fix to "insert after" the corresponding node.

Input JSON:
{$json}
PROMPT;
    }

    /**
     * @param  list<Criticism>  $criticisms
     */
    protected function buildFullArticleRectifyPrompt(
        Article $article,
        array $criticisms,
        ?SemanticContext $authorContext,
        ?SemanticContext $generalContext,
    ): string {
        $payload = [
            'article' => $article->toArray(),
            'criticisms' => array_map(
                static fn (Criticism $criticism): array => $criticism->toArray(),
                $criticisms
            ),
            'author_context' => $authorContext?->toArray(),
            'general_context' => $generalContext?->toArray(),
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a senior editorial writer revising an article based on critic feedback.

At least one criticism is not tied to a specific DOM reference, so you must revise the article holistically.
Apply the criticisms to improve the article while preserving structure and factual intent.
Return the full revised article body as Markdown (not HTML or JSON DOM). Use h2 and below for section headings; do not include h1.
For each criticism you addressed, record a rectification with concrete adjustments describing what you changed (use the criticism reference when present, otherwise null).

Rules:
- adjustments must be short, specific strings describing applied fixes.
- confidence is how confident you are the fix fully addresses the related criticism(s) (0.00–1.00).
- Only include rectifications for criticisms you actually addressed.

Input JSON:
{$json}
PROMPT;
    }

    /**
     * @param  list<string>  $elementReferences
     * @return array<string, mixed>
     */
    protected function buildTargetedRectifyArticleSchema(array $elementReferences): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'fixes' => [
                    'type' => 'array',
                    'minItems' => 0,
                    'maxItems' => min(count($elementReferences) * 5, 100),
                    'items' => $this->buildTargetedFixItemSchema(),
                ],
                'rectifications' => $this->buildRectificationsSchema(),
            ],
            'required' => ['fixes', 'rectifications'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildFullArticleRectifySchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'markdown' => [
                    'type' => 'string',
                    'description' => 'Revised article body in Markdown. Use h2+ for sections; do not include h1.',
                ],
                'rectifications' => [
                    'type' => 'array',
                    'items' => $this->buildRectificationItemSchema(referenceNullable: true),
                ],
            ],
            'required' => ['markdown', 'rectifications'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRectificationsSchema(): array
    {
        return [
            'type' => 'array',
            'items' => $this->buildRectificationItemSchema(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRectificationItemSchema(bool $referenceNullable = false): array
    {
        if ($referenceNullable) {
            $referenceSchema = [
                'type' => ['string', 'null'],
                'description' => 'DOM reference when the rectification maps to a section; null for article-wide changes.',
            ];
        } else {
            $referenceSchema = [
                'type' => 'string',
                'description' => 'DOM reference identifier for the revised section.',
            ];
        }

        return [
            'type' => 'object',
            'properties' => [
                'reference' => $referenceSchema,
                'confidence' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                    'description' => 'Confidence that the adjustments fully address the related criticism(s).',
                ],
                'adjustments' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 8,
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['reference', 'confidence', 'adjustments'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildTargetedFixItemSchema(): array
    {
        $referenceSchema = [
            'type' => 'string',
            'description' => 'DOM node identifier to target when this fix runs.',
        ];

        return [
            'anyOf' => [
                [
                    'type' => 'object',
                    'properties' => [
                        'reference' => $referenceSchema,
                        'operation' => [
                            'type' => 'string',
                            'const' => 'remove',
                            'description' => 'Remove the node at reference from the article.',
                        ],
                    ],
                    'required' => ['reference', 'operation'],
                    'additionalProperties' => false,
                ],
                [
                    'type' => 'object',
                    'properties' => [
                        'reference' => $referenceSchema,
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['replace', 'insert_before', 'insert_after'],
                            'description' => 'DOM mutation to apply at the referenced node.',
                        ],
                        'elements' => [
                            'type' => 'array',
                            'minItems' => 1,
                            'maxItems' => 10,
                            'items' => $this->buildDomElementSchema(),
                            'description' => 'One or more DOM nodes to insert or substitute.',
                        ],
                    ],
                    'required' => ['reference', 'operation', 'elements'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildDomElementSchema(int $depth = 0, int $maxDepth = 4): array
    {
        $elementTypes = array_values(array_filter(
            array_map(static fn (ElementType $type): string => $type->value, ElementType::cases()),
            static fn (string $type): bool => $type !== ElementType::ARTICLE->value,
        ));

        $childSchema = ['type' => 'string'];
        if ($depth < $maxDepth) {
            $childSchema = [
                'anyOf' => [
                    ['type' => 'string'],
                    $this->buildDomElementSchema($depth + 1, $maxDepth),
                ],
            ];
        }

        return [
            'type' => 'object',
            'properties' => [
                'identifier' => [
                    'type' => 'string',
                    'description' => 'Must match the fix reference identifier.',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => $elementTypes,
                ],
                'props' => [
                    'type' => 'object',
                    'properties' => (object) [],
                    'required' => [],
                    'additionalProperties' => false,
                ],
                'children' => [
                    'type' => 'array',
                    'items' => $childSchema,
                ],
            ],
            'required' => ['identifier', 'type', 'props', 'children'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hydrateTargetedRectifiedArticle(
        Article $article,
        array $payload,
    ): RectifiedArticle {

        $rectified = Article::fromArray($article->toArray());
        $appliedReferences = [];

        foreach ($payload['fixes'] ?? [] as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('Failed to rectify article with OpenAI: invalid fix row.');
            }

            $reference = trim((string) ($row['reference'] ?? ''));
            if ($reference === '' || $this->findElementByReference($rectified, $reference) === null) {
                continue;
            }

            if ($this->applyTargetedFix($rectified, $reference, $row)) {
                $appliedReferences[$reference] = true;
            }
        }

        if ($appliedReferences === []) {
            throw new RuntimeException('Failed to rectify article with OpenAI: no targeted fixes were applied.');
        }

        return (new RectifiedArticle)
            ->setArticle($rectified)
            ->setRectifications($this->hydrateRectificationsFromPayload($payload));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function applyTargetedFix(Article $rectified, string $reference, array $row): bool
    {
        $operation = $this->resolveTargetedFixOperation($row);

        if ($operation === 'remove') {
            $this->assertRemovableReference($rectified, $reference);

            if (! $rectified->removeChildByIdentifier($reference)) {
                throw new RuntimeException(
                    "Failed to rectify article with OpenAI: could not remove reference \"{$reference}\"."
                );
            }

            return true;
        }

        $elements = $this->hydrateFixElements($reference, $operation, $row);
        if ($elements === []) {
            throw new RuntimeException(
                "Failed to rectify article with OpenAI: fix for reference \"{$reference}\" must include elements for operation \"{$operation}\"."
            );
        }

        $applied = match ($operation) {
            'replace' => $rectified->replaceByIdentifier($reference, $elements),
            'insert_before' => $rectified->insertAllBefore($reference, $elements),
            'insert_after' => $rectified->insertAllAfter($reference, $elements),
            default => false,
        };

        if (! $applied) {
            throw new RuntimeException(
                "Failed to rectify article with OpenAI: could not apply operation \"{$operation}\" for reference \"{$reference}\"."
            );
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveTargetedFixOperation(array $row): string
    {
        $operation = strtolower(trim((string) ($row['operation'] ?? '')));
        if (in_array($operation, ['remove', 'replace', 'insert_before', 'insert_after'], true)) {
            return $operation;
        }

        if ((bool) ($row['removed'] ?? false)) {
            return 'remove';
        }

        return 'replace';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<Element>
     */
    protected function hydrateFixElements(string $reference, string $operation, array $row): array
    {
        $rawElements = $row['elements'] ?? null;
        if (! is_array($rawElements)) {
            $legacyElement = $row['element'] ?? null;
            if (is_array($legacyElement)) {
                $rawElements = [$legacyElement];
            } else {
                return [];
            }
        }

        $elements = [];
        foreach ($rawElements as $elementData) {
            if (! is_array($elementData)) {
                throw new RuntimeException('Failed to rectify article with OpenAI: invalid element in fix.');
            }

            $elements[] = Element::fromArray($elementData);
        }

        if ($operation === 'replace' && $elements !== [] && trim($elements[0]->getIdentifier()) !== $reference) {
            $elements[0]->setIdentifier($reference);
        }

        return $elements;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function hydrateFullArticleRectifiedArticle(array $payload): RectifiedArticle
    {
        $article = $this->buildArticleFromPayload($payload);
        if ($article->getChildren() === []) {
            throw new RuntimeException('Failed to rectify article with OpenAI: empty article body.');
        }

        return (new RectifiedArticle)
            ->setArticle($article)
            ->setRectifications($this->hydrateRectificationsFromPayload($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<Rectification>
     */
    protected function hydrateRectificationsFromPayload(array $payload): array
    {
        $rectifications = [];

        foreach ($payload['rectifications'] ?? [] as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('Failed to rectify article with OpenAI: invalid rectification row.');
            }

            try {
                $rectification = Rectification::fromArray($row);
            } catch (\InvalidArgumentException $e) {
                throw new RuntimeException(
                    'Failed to rectify article with OpenAI: '.$e->getMessage(),
                    0,
                    $e
                );
            }

            if ($rectification->getAdjustments() === []) {
                continue;
            }

            $rectifications[] = $rectification;
        }

        return $rectifications;
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
