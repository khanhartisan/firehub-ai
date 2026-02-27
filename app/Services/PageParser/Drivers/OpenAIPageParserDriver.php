<?php

namespace App\Services\PageParser\Drivers;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Contracts\PageParser\PageData;
use App\Services\PageParser\PageParserService;
use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

class OpenAIPageParserDriver extends PageParserService
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
     * Perform parsing using OpenAI with JSON schema structured output.
     */
    protected function performParsing(string $html): PageData
    {
        $prompt = $this->buildParsingPrompt($html);
        $jsonSchema = $this->buildJsonSchema();

        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->defaultModel)
            ->responseFormat([
                'type' => 'json_schema',
                'name' => 'page_parsing',
                'schema' => $jsonSchema,
                'strict' => true,
            ]);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (RequestException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to parse page with OpenAI: {$e->getMessage()}",
                0,
                $e
            );
        }

        // Check for refusals
        $this->checkForRefusal($response);

        $responseText = $response->getFirstOutputText();

        if (empty($responseText)) {
            throw new RuntimeException(
                'OpenAI returned empty parsing response'
            );
        }

        return $this->parseResponse($responseText);
    }

    /**
     * Build the parsing prompt for OpenAI.
     */
    protected function buildParsingPrompt(string $html): string
    {
        return <<<PROMPT
Analyze the following HTML page and extract structured data according to the provided schema.

Guidelines:
- title: Extract the main title of the page (from <title>, <h1>, or meta tags)
- excerpt: Extract a brief summary or description (from meta description, first paragraph, or excerpt)
- thumbnailUrl: Extract the main image URL (from og:image, twitter:image, or the first prominent image)
- markdownContent: Convert the main content of the page to clean markdown format, preserving structure
- publishedAt: Extract the publication date (from article:published_time, datePublished, or similar meta tags)
- updatedAt: Extract the last updated date (from article:modified_time, dateModified, or similar meta tags)
- canonicalUrl: Extract the canonical URL (from <link rel="canonical"> tag or canonical meta tag)
- canonicalNumber: Extract the page/episode/part number if applicable (e.g., page 2 of a category, episode 5 of a series, part 3 of an article). Return null if not applicable.

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
        return [
            'type' => 'object',
            'properties' => $properties = [
                'title' => [
                    'type' => 'string',
                    'description' => 'The main title of the page',
                ],
                'excerpt' => [
                    'type' => 'string',
                    'description' => 'A brief summary or description of the page content',
                ],
                'thumbnailUrl' => [
                    'type' => 'string',
                    'description' => 'The URL of the main thumbnail/image for the page',
                ],
                'markdownContent' => [
                    'type' => 'string',
                    'description' => 'The main content of the page converted to markdown format',
                ],
                'publishedAt' => [
                    'type' => ['string', 'null'],
                    'description' => 'The publication date in ISO 8601 format (YYYY-MM-DDTHH:mm:ssZ)',
                    'format' => 'date-time',
                ],
                'updatedAt' => [
                    'type' => ['string', 'null'],
                    'description' => 'The last updated date in ISO 8601 format (YYYY-MM-DDTHH:mm:ssZ)',
                    'format' => 'date-time',
                ],
                'canonicalUrl' => [
                    'type' => 'string',
                    'description' => 'The canonical URL of the page (from canonical link tag or meta tag)',
                ],
                'canonicalNumber' => [
                    'type' => ['integer', 'null'],
                    'description' => 'The page/episode/part number if applicable (e.g., page 2, episode 5, part 3). Null if not applicable.',
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
                        $refusalMessage = $content['refusal'] ?? 'The model refused to parse this page.';
                        throw new RuntimeException(
                            "OpenAI refused to parse the page: {$refusalMessage}"
                        );
                    }
                }
            }
        }
    }

    /**
     * Parse the response from OpenAI and create PageData object.
     */
    protected function parseResponse(string $responseText): PageData
    {
        $data = json_decode($responseText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Failed to parse response as JSON: '.json_last_error_msg()
            );
        }

        $pageData = new PageData();

        // Set title
        if (isset($data['title'])) {
            $pageData->setTitle($data['title']);
        }

        // Set excerpt
        if (isset($data['excerpt'])) {
            $pageData->setExcerpt($data['excerpt']);
        }

        // Set thumbnail URL
        if (isset($data['thumbnailUrl'])) {
            $pageData->setThumbnailUrl($data['thumbnailUrl']);
        }

        // Set markdown content
        if (isset($data['markdownContent'])) {
            $pageData->setMarkdownContent($data['markdownContent']);
        }

        // Set published date
        if (isset($data['publishedAt']) && !empty($data['publishedAt'])) {
            try {
                $publishedAt = Carbon::parse($data['publishedAt']);
                $pageData->setPublishedAt($publishedAt);
            } catch (\Exception $e) {
                // If parsing fails, leave as null
            }
        }

        // Set updated date
        if (isset($data['updatedAt']) && !empty($data['updatedAt'])) {
            try {
                $updatedAt = Carbon::parse($data['updatedAt']);
                $pageData->setUpdatedAt($updatedAt);
            } catch (\Exception $e) {
                // If parsing fails, leave as null
            }
        }

        // Set canonical URL
        if (isset($data['canonicalUrl'])) {
            $pageData->setCanonicalUrl($data['canonicalUrl']);
        }

        // Set canonical number
        if (isset($data['canonicalNumber'])) {
            $pageData->setCanonicalNumber($data['canonicalNumber'] !== null ? (int) $data['canonicalNumber'] : null);
        }

        return $pageData;
    }
}
