<?php

namespace App\Services\OpenAI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

/**
 * Structured JSON via POST /chat/completions for providers that do not expose the Responses API.
 */
class OpenAICompatibleChatCompletionsClient
{
    protected Client $client;

    protected string $defaultModel;

    public function __construct(array $config = [])
    {
        $apiKey = (string) ($config['api_key'] ?? '');
        $baseUrl = (string) ($config['base_url'] ?? 'https://api.openai.com/v1/');
        $this->defaultModel = (string) ($config['default_model'] ?? $config['model'] ?? 'gpt-4o-mini');

        $headers = [
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ];

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'headers' => $headers,
            'timeout' => (int) ($config['timeout'] ?? 120),
        ]);
    }

    /**
     * @param  array<string, mixed>  $jsonSchema
     * @return array<string, mixed>
     */
    public function requestStructuredJson(
        string $prompt,
        string $schemaName,
        array $jsonSchema,
        string $failureMessage,
        ?string $model = null,
        ?float $temperature = null,
        string $structuredOutput = 'json_schema',
    ): array {
        $payload = [
            'model' => $model ?? $this->defaultModel,
            'temperature' => $temperature ?? 0.3,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        if ($structuredOutput === 'json_object') {
            $payload['response_format'] = ['type' => 'json_object'];
        } else {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $schemaName,
                    'schema' => $jsonSchema,
                    'strict' => true,
                ],
            ];
        }

        try {
            $response = $this->client->post('chat/completions', [
                'json' => $payload,
                'timeout' => 300,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException(
                "{$failureMessage}: {$e->getMessage()}",
                0,
                $e
            );
        }

        $data = json_decode((string) $response->getBody(), true);
        if (! is_array($data)) {
            throw new RuntimeException("{$failureMessage}: invalid response JSON.");
        }

        $message = $data['choices'][0]['message'] ?? null;
        if (! is_array($message)) {
            throw new RuntimeException("{$failureMessage}: missing chat completion message.");
        }

        if (isset($message['refusal']) && is_string($message['refusal']) && $message['refusal'] !== '') {
            throw new RuntimeException("OpenAI-compatible provider refused the request: {$message['refusal']}");
        }

        $text = isset($message['content']) ? trim((string) $message['content']) : '';
        if ($text === '') {
            throw new RuntimeException("{$failureMessage}: empty model output.");
        }

        $decoded = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new RuntimeException(
                "{$failureMessage}: invalid JSON (".json_last_error_msg().').'
            );
        }

        return $decoded;
    }
}
