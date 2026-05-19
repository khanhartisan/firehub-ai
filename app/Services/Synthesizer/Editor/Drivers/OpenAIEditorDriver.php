<?php

namespace App\Services\Synthesizer\Editor\Drivers;

use App\Contracts\CommonData\IdentifiableSemanticContext;
use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;
use App\Services\Synthesizer\Editor\EditorService;
use RuntimeException;

class OpenAIEditorDriver extends EditorService
{
    protected ?OpenAIClient $openAIClient;

    /** @var array<string, mixed> */
    protected array $config;

    protected BasicEditorDriver $fallback;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        ?OpenAIClient $openAIClient = null,
        array $config = [],
        ?BasicEditorDriver $fallback = null,
    ) {
        $this->openAIClient = $openAIClient;
        $this->config = array_merge(SynthesizerSubserviceConfig::settings('editor'), $config);
        $this->fallback = $fallback ?? new BasicEditorDriver;
    }

    public function determineAuthorContext(Idea $idea, array $authorContexts): SemanticContext
    {
        $contexts = array_values(array_filter(
            $authorContexts,
            static fn (mixed $context): bool => $context instanceof SemanticContext
        ));

        if ($contexts === []) {
            throw new \InvalidArgumentException('At least one author context is required.');
        }

        if (count($contexts) === 1) {
            return $contexts[0]->clone();
        }

        if (! $this->openAIClient instanceof OpenAIClient) {
            return $this->fallback->determineAuthorContext($idea, $contexts);
        }

        try {
            $picked = $this->pickAuthorContextWithOpenAI($idea, $contexts);
            if ($picked instanceof SemanticContext) {
                return $picked->clone();
            }
        } catch (RuntimeException) {
            // Fall through to deterministic picker.
        }

        return $this->fallback->determineAuthorContext($idea, $contexts);
    }

    public function distillOutlineAuthorContext(
        Outline $outline,
        string $outlineItemIdentifier,
        SemanticContext $authorContext,
        ?SemanticContext $generalContext = null
    ): SemanticContext {
        if (! $this->openAIClient instanceof OpenAIClient) {
            return $this->fallback->distillOutlineAuthorContext(
                $outline,
                $outlineItemIdentifier,
                $authorContext,
                $generalContext
            );
        }

        try {
            $distilled = $this->distillWithOpenAI(
                $outline,
                $outlineItemIdentifier,
                $authorContext,
                $generalContext
            );
            if ($distilled instanceof SemanticContext) {
                return $distilled;
            }
        } catch (RuntimeException) {
            // Fall through to deterministic distillation.
        }

        return $this->fallback->distillOutlineAuthorContext(
            $outline,
            $outlineItemIdentifier,
            $authorContext,
            $generalContext
        );
    }

    /**
     * @param  list<SemanticContext>  $contexts
     */
    protected function pickAuthorContextWithOpenAI(Idea $idea, array $contexts): ?SemanticContext
    {
        $candidates = [];
        foreach ($contexts as $index => $context) {
            $candidates[] = [
                'author_context_identifier' => $this->resolveAuthorContextIdentifier($context, $index),
                'context' => $context->toArray(),
            ];
        }

        $payload = [
            'idea' => $idea->toArray(),
            'candidates' => $candidates,
        ];

        $data = $this->requestStructuredJson(
            $this->buildDetermineAuthorContextPrompt($payload),
            'editor_determine_author_context',
            $this->buildDetermineAuthorContextSchema(),
            'Failed to determine author context with OpenAI',
        );

        $identifier = trim((string) ($data['author_context_identifier'] ?? ''));
        if ($identifier === '') {
            return null;
        }

        foreach ($contexts as $index => $context) {
            if ($this->resolveAuthorContextIdentifier($context, $index) === $identifier) {
                return $context;
            }
        }

        return null;
    }

    protected function distillWithOpenAI(
        Outline $outline,
        string $outlineItemIdentifier,
        SemanticContext $authorContext,
        ?SemanticContext $generalContext,
    ): ?SemanticContext {
        $item = $this->findOutlineItem($outline, $outlineItemIdentifier);
        if (! $item instanceof OutlineItem) {
            throw new \InvalidArgumentException(sprintf(
                'Outline item "%s" was not found.',
                $outlineItemIdentifier
            ));
        }

        $authorKeys = array_keys($authorContext->toArray());
        $generalKeys = $generalContext instanceof SemanticContext
            ? array_intersect(
                ['article_context', 'client_context', 'outline_focus'],
                array_keys($generalContext->toArray())
            )
            : [];

        $payload = [
            'outline' => $outline->toArray(),
            'outline_item_identifier' => $outlineItemIdentifier,
            'author_context' => $authorContext->toArray(),
            'author_context_keys' => array_values($authorKeys),
            'general_context' => $generalContext?->toArray(),
            'general_context_keys' => array_values($generalKeys),
        ];

        $data = $this->requestStructuredJson(
            $this->buildDistillPrompt($payload),
            'editor_distill_outline_author_context',
            $this->buildDistillSchema($authorKeys, $generalKeys),
            'Failed to distill outline author context with OpenAI',
        );

        return $this->hydrateDistilledContext(
            $authorContext,
            $item,
            $generalContext,
            $data
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function buildDetermineAuthorContextPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are an editorial director choosing which author persona should write a given article idea.

Given the idea and candidate author contexts, return exactly one "author_context_identifier" from the input candidates — the persona whose voice, expertise, and worldview best fit the idea and will serve readers best.

Prefer the strongest editorial fit, not generic neutrality. Use only identifiers present in the input.

Input JSON:
{$json}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function buildDistillPrompt(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are an editorial director preparing a focused author brief for one outline section.

Given the full outline, the target outline item, the full author context, and optional general pipeline context:
- Choose which top-level author_context keys to retain for this section only ("retained_keys"). Omit keys that are irrelevant or distracting for this section.
- Choose which general_context keys to surface ("general_keys"). Only use keys listed in general_context_keys.
- Write concise "section_editorial_notes" telling the writer how to approach this section in the author's voice (angle, emphasis, what to avoid).

Do not invent facts. Do not include weights. Keep retained_keys and general_keys as subsets of the provided key lists.

Input JSON:
{$json}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildDetermineAuthorContextSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $properties = [
                'author_context_identifier' => [
                    'type' => 'string',
                    'description' => 'Identifier of the chosen author context from the input candidates.',
                ],
                'rationale' => [
                    'type' => 'string',
                    'description' => 'Brief reason for the choice.',
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  list<string>  $authorKeys
     * @param  list<string>  $generalKeys
     * @return array<string, mixed>
     */
    protected function buildDistillSchema(array $authorKeys, array $generalKeys): array
    {
        $authorKeyEnum = $authorKeys === [] ? ['__none__'] : $authorKeys;
        $generalKeyEnum = $generalKeys === [] ? ['__none__'] : $generalKeys;

        return [
            'type' => 'object',
            'properties' => $properties = [
                'retained_keys' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => $authorKeyEnum,
                    ],
                    'description' => 'Author context keys to keep for this section.',
                ],
                'general_keys' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => $generalKeyEnum,
                    ],
                    'description' => 'General context keys to include for this section.',
                ],
                'section_editorial_notes' => [
                    'type' => 'string',
                    'description' => 'Concise editor guidance for this outline section.',
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function hydrateDistilledContext(
        SemanticContext $authorContext,
        OutlineItem $item,
        ?SemanticContext $generalContext,
        array $data,
    ): SemanticContext {
        $distilled = $this->newContextLike($authorContext);
        $source = $authorContext->toArray();

        $retainedKeys = $this->filterSchemaKeys(
            $data['retained_keys'] ?? [],
            array_keys($source)
        );
        foreach ($retainedKeys as $key) {
            $entry = $source[$key] ?? null;
            if (! is_array($entry) || ! array_key_exists('value', $entry) || ! is_string($entry['description'] ?? null)) {
                continue;
            }
            if (! $this->contextEntryHasContent($entry['value'])) {
                continue;
            }

            $distilled->set($key, $entry['description'], $entry['value']);
        }

        $this->applySectionFields($distilled, $item);

        if ($generalContext instanceof SemanticContext) {
            $generalKeys = $this->filterSchemaKeys(
                $data['general_keys'] ?? [],
                ['article_context', 'client_context', 'outline_focus']
            );
            foreach ($generalKeys as $key) {
                $entry = $generalContext->get($key);
                if ($entry === null || ! $this->contextEntryHasContent($entry['value'] ?? null)) {
                    continue;
                }

                $distilled->set(
                    'general_'.$key,
                    'General pipeline context relevant to this section.',
                    $entry['value']
                );
            }
        }

        $notes = trim((string) ($data['section_editorial_notes'] ?? ''));
        if ($notes !== '') {
            $distilled->set(
                'section_editorial_notes',
                'Editorial notes for the outline section being written.',
                $notes
            );
        }

        if ($distilled->toArray() === []) {
            return null;
        }

        return $distilled;
    }

    protected function applySectionFields(SemanticContext $distilled, OutlineItem $item): void
    {
        $point = $item->getPoint();
        $headline = trim((string) ($point->getHeadline() ?? ''));
        if ($headline !== '') {
            $distilled->set(
                'section_headline',
                'Headline for the outline section being written.',
                $headline
            );
        }

        $description = trim((string) $point->getDescription());
        if ($description !== '') {
            $distilled->set(
                'section_description',
                'Description for the outline section being written.',
                $description
            );
        }

        $guidelines = array_values(array_filter(array_map(
            static fn (mixed $line): string => trim((string) $line),
            $item->getGuidelines()
        )));
        if ($guidelines !== []) {
            $distilled->set(
                'section_guidelines',
                'Writing guidelines for the outline section being written.',
                $guidelines
            );
        }
    }

    /**
     * @param  mixed  $rawKeys
     * @param  list<string>  $allowed
     * @return list<string>
     */
    protected function filterSchemaKeys(mixed $rawKeys, array $allowed): array
    {
        if (! is_array($rawKeys)) {
            return [];
        }

        $allowedLookup = array_fill_keys($allowed, true);
        $keys = [];
        foreach ($rawKeys as $rawKey) {
            $key = trim((string) $rawKey);
            if ($key === '' || $key === '__none__' || ! isset($allowedLookup[$key])) {
                continue;
            }
            $keys[] = $key;
        }

        return array_values(array_unique($keys));
    }

    protected function resolveAuthorContextIdentifier(SemanticContext $context, int $index): string
    {
        if ($context instanceof IdentifiableSemanticContext) {
            $identifier = trim((string) $context->getIdentifier());
            if ($identifier !== '') {
                return $identifier;
            }
        }

        return 'author-context-'.$index;
    }

    protected function findOutlineItem(Outline $outline, string $identifier): ?OutlineItem
    {
        foreach ($outline->getItems() as $item) {
            $match = $this->findOutlineItemRecursive($item, $identifier);
            if ($match instanceof OutlineItem) {
                return $match;
            }
        }

        return null;
    }

    protected function findOutlineItemRecursive(OutlineItem $item, string $identifier): ?OutlineItem
    {
        if ($item->getIdentifier() === $identifier) {
            return $item;
        }

        foreach ($item->getSubItems() as $subItem) {
            $match = $this->findOutlineItemRecursive($subItem, $identifier);
            if ($match instanceof OutlineItem) {
                return $match;
            }
        }

        return null;
    }

    protected function newContextLike(SemanticContext $context): SemanticContext
    {
        if ($context instanceof AuthorContext) {
            return new AuthorContext;
        }

        return new SemanticContext;
    }

    protected function contextEntryHasContent(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_int($value) || is_float($value)) {
            return true;
        }

        if (! is_array($value)) {
            return true;
        }

        if ($value === []) {
            return false;
        }

        foreach ($value as $nested) {
            if ($this->contextEntryHasContent($nested)) {
                return true;
            }
        }

        return false;
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
