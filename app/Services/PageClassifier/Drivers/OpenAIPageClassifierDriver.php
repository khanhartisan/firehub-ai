<?php

namespace App\Services\PageClassifier\Drivers;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\PageClassifier\ClassificationResult;
use App\Enums\ContentType;
use App\Enums\PageType;
use App\Enums\Temporal;
use App\Services\PageClassifier\PageClassifierService;
use RuntimeException;

class OpenAIPageClassifierDriver extends PageClassifierService
{
    protected OpenAIClient $openAIClient;

    protected string $defaultModel;

    public function __construct(OpenAIClient $openAIClient, array $config = [])
    {
        parent::__construct($config);

        $this->openAIClient = $openAIClient;
        $this->defaultModel = $config['model'] ?? 'gpt-4o-mini';
    }

    /**
     * Perform classification using OpenAI with JSON schema structured output.
     */
    protected function performClassification(string $html): ClassificationResult
    {
        $prompt = $this->buildClassificationPrompt($html);
        $jsonSchema = $this->buildJsonSchema();

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->defaultModel)
            ->responseFormat([
                'type' => 'json_schema',
                'name' => 'page_classification',
                'schema' => $jsonSchema,
                'strict' => true,
            ]);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to classify page with OpenAI: {$e->getMessage()}",
                0,
                $e
            );
        }

        // Check for refusals
        $this->checkForRefusal($response);

        $responseText = $response->getFirstOutputText();

        if (empty($responseText)) {
            throw new RuntimeException(
                'OpenAI returned empty classification response'
            );
        }

        return $this->parseClassificationResponse($responseText);
    }

    /**
     * Build the classification prompt for OpenAI.
     */
    protected function buildClassificationPrompt(string $html): string
    {
        return <<<PROMPT
Analyze the following HTML page and classify it according to the provided schema.

Guidelines:
- content_type: Identify the primary type of content based on the page's main purpose
- page_type: Determine if this is a listing page (multiple items), detail page (single item), redirect page, or unknown
- temporal: Classify the temporal nature of the content (evergreen, breaking, seasonal, trending, or topical)
- tags: Extract 3-10 relevant tags that describe the content (lowercase, no spaces, use underscores)

HTML Content:
{$html}
PROMPT;
    }

    /**
     * Build the JSON schema for structured output.
     *
     * @return array<string, mixed>
     */
    protected function buildJsonSchema(): array
    {
        $contentTypeEnum = array_map(
            fn (ContentType $type) => $type->value,
            ContentType::cases()
        );

        $pageTypeEnum = array_map(
            fn (PageType $type) => $type->value,
            PageType::cases()
        );

        $temporalEnum = array_map(
            fn (Temporal $type) => $type->value,
            Temporal::cases()
        );

        return [
            'type' => 'object',
            'properties' => $properties = [
                'content_type' => [
                    'type' => 'string',
                    'description' => 'The primary type of content on the page',
                    'enum' => $contentTypeEnum,
                ],
                'page_type' => [
                    'type' => 'string',
                    'description' => 'The type of page structure',
                    'enum' => $pageTypeEnum,
                ],
                'temporal' => [
                    'type' => 'string',
                    'description' => 'The temporal nature of the content',
                    'enum' => $temporalEnum,
                ],
                'tags' => [
                    'type' => 'array',
                    'description' => 'Relevant tags describing the content (lowercase)',
                    'items' => [
                        'type' => 'string',
                    ],
                    'minItems' => 0,
                    'maxItems' => 10,
                ],
            ],
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * Check for refusal in the response.
     */
    protected function checkForRefusal($response): void
    {
        foreach ($response->getOutput() as $item) {
            if (($item['type'] ?? null) === 'message' && isset($item['content'])) {
                foreach ($item['content'] as $content) {
                    if (($content['type'] ?? null) === 'refusal') {
                        $refusalMessage = $content['refusal'] ?? 'The model refused to classify this page.';
                        throw new RuntimeException(
                            "OpenAI refused to classify the page: {$refusalMessage}"
                        );
                    }
                }
            }
        }
    }

    /**
     * Parse the classification response from OpenAI.
     * With structured outputs, the response should already be valid JSON matching our schema.
     */
    protected function parseClassificationResponse(string $responseText): ClassificationResult
    {
        $data = json_decode($responseText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Failed to parse classification response as JSON: '.json_last_error_msg()
            );
        }

        $result = new ClassificationResult();

        // Set content type
        if (isset($data['content_type'])) {
            $contentType = ContentType::tryFrom($data['content_type']);
            if ($contentType !== null) {
                $result->setContentType($contentType);
            } else {
                throw new RuntimeException(
                    "Invalid content_type value: {$data['content_type']}"
                );
            }
        }

        // Set page type
        if (isset($data['page_type'])) {
            $pageType = PageType::tryFrom($data['page_type']);
            if ($pageType !== null) {
                $result->setPageType($pageType);
            } else {
                throw new RuntimeException(
                    "Invalid page_type value: {$data['page_type']}"
                );
            }
        }

        // Set temporal
        if (isset($data['temporal'])) {
            $temporal = Temporal::tryFrom($data['temporal']);
            if ($temporal !== null) {
                $result->setTemporal($temporal);
            } else {
                throw new RuntimeException(
                    "Invalid temporal value: {$data['temporal']}"
                );
            }
        }

        // Set tags
        if (isset($data['tags']) && is_array($data['tags'])) {
            $result->setTags($data['tags']);
        }

        return $result;
    }
}
