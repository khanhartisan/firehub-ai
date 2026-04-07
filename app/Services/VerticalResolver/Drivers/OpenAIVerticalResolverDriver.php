<?php

namespace App\Services\VerticalResolver\Drivers;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\VerticalResolver\Vertical;
use App\Contracts\VerticalResolver\VerticalMatch;
use App\Contracts\VerticalResolver\VerticalResolver;
use App\Utils\HtmlCleaner;
use Illuminate\Support\Str;
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

        $prompt = $this->buildProposePrompt($content, $verticals);
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

    /**
     * @param  Vertical[]  $existingVerticals  Current vertical roots (with optional children) so the model can avoid duplicates and extend the tree
     */
    protected function buildProposePrompt(string $content, array $existingVerticals): string
    {
        $existingSection = $this->formatExistingVerticalsForPrompt($existingVerticals);

        return <<<PROMPT
You are designing a 3-level hierarchy of business verticals (domains) based on the content below.

EXISTING VERTICALS (already in the system — do not duplicate these names or suggest synonyms; you may suggest NEW children under existing macro/industry nodes when the content fits):

{$existingSection}

LEVEL DEFINITIONS

Level 1 — Macro Domain
- Broad, stable industry domains.
- Must be long-lived and widely recognized sectors.
- Examples: Technology, Finance, Healthcare, Real Estate, Education, Travel.

Level 2 — Industry Segment
- A major subdivision within a macro domain.
- Represents a distinct industry area with its own ecosystem.
- Must still be broadly recognizable.
- Examples:
  Technology → Artificial Intelligence
  Finance → Banking
  Real Estate → Residential Real Estate

Level 3 — Specialized Segment
- A focused but still industry-level specialization.
- Must describe a domain of activity, NOT a product type.
- Should still aggregate many sources and websites.
- Examples:
  Artificial Intelligence → Generative AI
  Banking → Digital Banking
  Residential Real Estate → Property Listings

STRICT RULES

- Do NOT create product categories (e.g., "Shoes", "Laptops").
- Do NOT create overly niche topics.
- Do NOT exceed 3 levels.
- Each level must become MORE specific but remain an industry domain.
- Level 3 must still represent a scalable data ecosystem.
- Avoid duplicates or synonyms of existing domains. Only suggest NEW verticals that are not already in the list above.

OUTPUT FORMAT

Return a JSON object with a single key "proposals": an array of vertical trees. Each tree node has:
- name: string (lowercase identifier, e.g. "technology", "artificial_intelligence", "generative_ai")
- description: string (short description; may be empty "")
- children: array of child nodes (same structure). Level 1 has Level 2 children; Level 2 has Level 3 children; Level 3 has an empty children array; you may return children as an empty array at any level if not needed.

Content:
{$content}
PROMPT;
    }

    /**
     * Format existing verticals (roots with children) for the prompt so the model can avoid duplicates.
     *
     * @param  Vertical[]  $verticals
     */
    protected function formatExistingVerticalsForPrompt(array $verticals): string
    {
        if ($verticals === []) {
            return '(none)';
        }

        $lines = [];
        foreach ($verticals as $v) {
            $this->appendVerticalTreeToLines($v, 0, $lines);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  string[]  $lines
     */
    private function appendVerticalTreeToLines(Vertical $v, int $depth, array &$lines): void
    {
        $indent = str_repeat('  ', $depth);
        $name = $v->getName();
        $desc = $v->getDescription() ?? '';
        $id = $v->getIdentifier();
        $line = $indent . '- ' . $name;
        if ($desc !== '') {
            $line .= ': ' . $desc;
        }
        if ($id !== null && $id !== '') {
            $line .= ' [id: ' . $id . ']';
        }
        $lines[] = $line;

        foreach ($v->getChildren() as $child) {
            $this->appendVerticalTreeToLines($child, $depth + 1, $lines);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildProposeJsonSchema(): array
    {
        // Nested 3-level schema using "children" arrays. No recursion: each level references the next.
        // Level 3 (leaf): name, description, children (empty array).
        $level3Node = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Level 3 specialized segment name (lowercase identifier, example: Artificial Intelligence → Generative AI, Banking → Digital Banking, Residential Real Estate → Property Listings...)',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Level 3 description (may be empty)',
                ]
            ],
            'required' => ['name', 'description'],
            'additionalProperties' => false,
        ];

        // Level 2: name, description, children (array of Level 3 nodes).
        $level2Node = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Level 2 industry segment name (lowercase identifier, example: Technology → Artificial Intelligence, Finance → Banking, Real Estate → Residential Real Estate...)',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Level 2 description (may be empty)',
                ],
                'children' => [
                    'type' => 'array',
                    'description' => 'Level 3 specialized segments',
                    'items' => $level3Node,
                ],
            ],
            'required' => ['name', 'description', 'children'],
            'additionalProperties' => false,
        ];

        // Level 1 (root): name, description, children (array of Level 2 nodes).
        $level1Node = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Level 1 macro domain name (lowercase identifier, example: Technology, Finance, Healthcare, Real Estate, Education, Travel...)',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Level 1 macro domain description (may be empty)',
                ],
                'children' => [
                    'type' => 'array',
                    'description' => 'Level 2 industry segments',
                    'items' => $level2Node,
                ],
            ],
            'required' => ['name', 'description', 'children'],
            'additionalProperties' => false,
        ];

        return [
            'type' => 'object',
            'properties' => [
                'proposals' => [
                    'type' => 'array',
                    'description' => 'Suggested vertical trees (macro domain with children → industry segment with children → specialized segment)',
                    'items' => $level1Node,
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

        $items = $data['proposals'] ?? [];

        $roots = [];
        foreach ($items as $node) {
            $vertical = $this->parseProposeNode($node);
            if ($vertical !== null) {
                $roots[] = $vertical;
            }
        }

        return $roots;
    }

    /**
     * Recursively parse a single node (name, description, children) into a Vertical tree (max 3 levels).
     */
    private function parseProposeNode(array $node): ?Vertical
    {
        $name = $node['name'] ?? '';
        if ($name === '') {
            return null;
        }

        $description = isset($node['description']) ? (string) $node['description'] : null;
        $vertical = new Vertical(Str::slug($name), $description);

        $children = $node['children'] ?? [];
        if (! is_array($children)) {
            return $vertical;
        }

        foreach ($children as $childNode) {
            if (! is_array($childNode)) {
                continue;
            }
            $childVertical = $this->parseProposeNode($childNode);
            if ($childVertical !== null) {
                $vertical->addChild($childVertical);
            }
        }

        return $vertical;
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
