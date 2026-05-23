<?php

namespace App\Services\OpenAI\Drivers;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Enums\OpenAI\ResponseStatus;
use App\Utils\Debugger;
use App\Utils\Str;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * OpenAI-compatible client backed by POST /chat/completions (Ollama, vLLM, etc.).
 */
class ChatCompletionsDriver implements OpenAIClient
{
    protected Client $client;

    protected string $defaultModel;

    protected ?string $baseUrl = null;

    public function __construct(array $config = [])
    {
        $apiKey = (string) ($config['api_key'] ?? '');
        $this->baseUrl = (string) ($config['base_url'] ?? 'https://api.openai.com/v1/');
        $this->defaultModel = (string) ($config['default_model'] ?? $config['model'] ?? 'gpt-4o-mini');

        $headers = [
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ];

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => $headers,
            'timeout' => (int) ($config['timeout'] ?? 120),
        ]);
    }

    public function createResponse(ResponseInput $input, ?ResponseOptions $options = null): Response
    {
        $format = $options?->getResponseFormat();
        $model = $options?->getModel() ?? $this->defaultModel;
        $temperature = $options?->getTemperature();

        if (is_array($format) && ($format['type'] ?? null) === 'json_schema') {
            $prompt = $this->extractPromptText($input);
            $schema = $format['schema'] ?? [];
            $schemaName = (string) ($format['name'] ?? 'structured_output');

            Debugger::devConsoleDump(
                '-- Sending request to OpenAI (compatible) API: '.$this->baseUrl.' 
                / Model: '.($options?->getModel() ?? $this->defaultModel).' 
                / Payload length: '.($payloadLength = strlen($prompt)).'
                / Payload: '.($payloadLength <= 5000 ? $prompt : (function () use ($prompt) {
                    $payloadDumpPath = 'logs/debugger/dumps/'.Str::ulid().'.txt';
                    Storage::disk('local')->put($payloadDumpPath, $prompt);
                    return 'Too long to dump. Dumped to: '.$payloadDumpPath;
                })())
            );

            $data = $this->requestStructuredJson(
                $prompt,
                $schemaName,
                is_array($schema) ? $schema : [],
                'Failed to create OpenAI-compatible chat completion',
                $model,
                $temperature,
            );

            Debugger::devConsoleDump(
                '---- Response payload length: '.($outputLength = strlen(json_encode($data))).'
                / Payload JSON: '.($outputLength <= 5000 ? json_encode($data) : (function () use ($data) {
                    $payloadDumpPath = 'logs/debugger/dumps/'.Str::ulid().'.txt';
                    Storage::disk('local')->put($payloadDumpPath, json_encode($data));
                    return 'Too long to dump. Dumped to: '.$payloadDumpPath;
                })())
            );

            return $this->responseFromText(json_encode($data, JSON_THROW_ON_ERROR), $model);
        }

        Debugger::devConsoleDump(
            '-- Sending request to OpenAI (compatible) API: '.$this->baseUrl.' 
                / Model: '.($options?->getModel() ?? $this->defaultModel).' 
                / Payload length: '.($payloadLength = strlen($input->toJson())).'
                / Payload: '.($payloadLength <= 5000 ? $input->toJson() : (function () use ($input) {
                $payloadDumpPath = 'logs/debugger/dumps/'.Str::ulid().'.txt';
                Storage::disk('local')->put($payloadDumpPath, $input->toJson());
                return 'Too long to dump. Dumped to: '.$payloadDumpPath;
            })())
        );

        $text = $this->requestUnstructuredCompletion($input, $model, $temperature);

        Debugger::devConsoleDump(
            '---- Response payload length: '.($outputLength = strlen($text)).'
                / Payload JSON: '.($outputLength <= 5000 ? json_encode($text) : (function () use ($text) {
                $payloadDumpPath = 'logs/debugger/dumps/'.Str::ulid().'.txt';
                Storage::disk('local')->put($payloadDumpPath, $text);
                return 'Too long to dump. Dumped to: '.$payloadDumpPath;
            })())
        );

        return $this->responseFromText($text, $model);
    }

    public function getResponse(string $responseId): Response
    {
        throw new RuntimeException('Chat completions driver does not support fetching responses by ID.');
    }

    public function cancelResponse(string $responseId): Response
    {
        throw new RuntimeException('Chat completions driver does not support cancelling responses.');
    }

    public function deleteResponse(string $responseId): Response
    {
        throw new RuntimeException('Chat completions driver does not support deleting responses.');
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
            Debugger::devConsoleDump($data);
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

    protected function extractPromptText(ResponseInput $input): string
    {
        $parts = [];

        foreach ($input->getMessages() as $message) {
            foreach ($message['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'input_text' && isset($content['text'])) {
                    $parts[] = (string) $content['text'];
                }
            }
        }

        $text = trim(implode("\n\n", $parts));

        if ($text === '') {
            throw new RuntimeException('Chat completions driver requires at least one input_text message.');
        }

        return $text;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildChatMessages(ResponseInput $input): array
    {
        $messages = [];

        foreach ($input->getMessages() as $message) {
            $role = (string) ($message['role'] ?? 'user');
            $contentParts = [];
            $textParts = [];

            foreach ($message['content'] ?? [] as $content) {
                $type = $content['type'] ?? null;

                if ($type === 'input_text' && isset($content['text'])) {
                    $textParts[] = (string) $content['text'];
                } elseif ($type === 'input_image' && isset($content['image_url'])) {
                    if ($textParts !== []) {
                        $contentParts[] = [
                            'type' => 'text',
                            'text' => implode("\n\n", $textParts),
                        ];
                        $textParts = [];
                    }

                    $image = [
                        'type' => 'image_url',
                        'image_url' => ['url' => (string) $content['image_url']],
                    ];

                    if (isset($content['detail'])) {
                        $image['image_url']['detail'] = $content['detail'];
                    }

                    $contentParts[] = $image;
                }
            }

            if ($textParts !== []) {
                $contentParts[] = [
                    'type' => 'text',
                    'text' => implode("\n\n", $textParts),
                ];
            }

            if ($contentParts === []) {
                continue;
            }

            $messages[] = [
                'role' => $role,
                'content' => count($contentParts) === 1 && ($contentParts[0]['type'] ?? null) === 'text'
                    ? $contentParts[0]['text']
                    : $contentParts,
            ];
        }

        if ($messages === []) {
            throw new RuntimeException('Chat completions driver requires at least one message with content.');
        }

        return $messages;
    }

    protected function requestUnstructuredCompletion(
        ResponseInput $input,
        ?string $model,
        ?float $temperature,
    ): string {
        $payload = [
            'model' => $model ?? $this->defaultModel,
            'messages' => $this->buildChatMessages($input),
        ];

        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        try {
            $response = $this->client->post('chat/completions', [
                'json' => $payload,
                'timeout' => 300,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException(
                "Failed to create OpenAI-compatible chat completion: {$e->getMessage()}",
                0,
                $e
            );
        }

        $data = json_decode((string) $response->getBody(), true);
        if (! is_array($data)) {
            throw new RuntimeException('Failed to create OpenAI-compatible chat completion: invalid response JSON.');
        }

        $message = $data['choices'][0]['message'] ?? null;
        if (! is_array($message)) {
            throw new RuntimeException('Failed to create OpenAI-compatible chat completion: missing message.');
        }

        if (isset($message['refusal']) && is_string($message['refusal']) && $message['refusal'] !== '') {
            throw new RuntimeException("OpenAI-compatible provider refused the request: {$message['refusal']}");
        }

        $text = isset($message['content']) ? trim((string) $message['content']) : '';
        if ($text === '') {
            throw new RuntimeException('Failed to create OpenAI-compatible chat completion: empty model output.');
        }

        return $text;
    }

    protected function responseFromText(string $text, ?string $model): Response
    {
        return Response::fromArray([
            'id' => 'chatcmpl-adapted',
            'created_at' => time(),
            'status' => ResponseStatus::COMPLETED->value,
            'completed_at' => time(),
            'model' => $model ?? '',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => $text,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
