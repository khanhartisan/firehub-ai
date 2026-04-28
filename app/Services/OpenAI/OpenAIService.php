<?php

namespace App\Services\OpenAI;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response as ResponseObject;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Utils\Debugger;
use App\Utils\Str;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OpenAIService implements OpenAIClient
{
    protected Client $client;

    protected string $apiKey;

    protected string $baseUrl;

    protected string $defaultModel;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->baseUrl = $config['base_url'] ?? 'https://api.openai.com/v1/';
        $this->defaultModel = $config['default_model'] ?? 'gpt-4o-mini';

        $headers = [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ];

        // Add beta header if specified (OpenAI Responses API requires it)
        if (isset($config['beta_header']) && $config['beta_header']) {
            $headers['OpenAI-Beta'] = $config['beta_header'];
        }

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => $headers,
            'timeout' => $config['timeout'] ?? 60,
        ]);
    }

    /**
     * Create a model response.
     */
    public function createResponse(ResponseInput $input, ?ResponseOptions $options = null): ResponseObject
    {
        $payload = $this->buildPayload($input, $options);

        try {
            Debugger::devConsoleDump(
                '-- Sending request to OpenAI (compatible) API: '.$this->baseUrl.' 
                / Model: '.($options?->getModel() ?? $this->defaultModel).' 
                / Payload length: '.($payloadLength = strlen(json_encode($payload))).'
                / Payload JSON: '.($payloadLength <= 5000 ? json_encode($payload) : (function () use ($payload) {
                    $payloadDumpPath = 'logs/debugger/dumps/'.Str::ulid().'.txt';
                    Storage::disk('local')->put($payloadDumpPath, json_encode($payload));
                    return 'Too long to dump. Dumped to: '.$payloadDumpPath;
                })())
            );

            $response = $this->client->post('responses', [
                'json' => $payload,
                'timeout' => 300,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            Debugger::devConsoleDump(
                '---- Response payload length: '.strlen(json_encode($data)).'
                / Payload JSON: '.json_encode($data)
            );

            return ResponseObject::fromArray($data);
        } catch (BadResponseException $e) {
            Log::error('OpenAI API error', $errorLogs = [
                'error' => $e->getMessage(),
                'payload' => $payload,
                'response' => (string) $e->getResponse()->getBody(),
            ]);

            throw $e;
        } catch (GuzzleException $e) {
            Log::error('OpenAI API error', $errorLogs = [
                'error' => $e->getMessage(),
                'payload' => $payload,
                'response' => method_exists($e, 'getResponse') ? (string) $e->getResponse()?->getBody() : 'Connection error',
            ]);

            throw new \RuntimeException('Failed to create OpenAI response: '.$e->getMessage(), 0, $e);

        } finally {
            if (isset($errorLogs)) {
                Debugger::devConsoleDump($errorLogs);
            }
        }
    }

    /**
     * Get a previously created response.
     */
    public function getResponse(string $responseId): ResponseObject
    {
        try {
            $response = $this->client->get("responses/{$responseId}");

            $data = json_decode($response->getBody()->getContents(), true);

            return ResponseObject::fromArray($data);
        } catch (GuzzleException $e) {
            Log::error('OpenAI API error', [
                'error' => $e->getMessage(),
                'response_id' => $responseId,
            ]);

            throw new \RuntimeException('Failed to get OpenAI response: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Cancel an in-progress response.
     */
    public function cancelResponse(string $responseId): ResponseObject
    {
        try {
            $response = $this->client->post("responses/{$responseId}/cancel", []);

            $data = json_decode($response->getBody()->getContents(), true);

            return ResponseObject::fromArray($data);
        } catch (GuzzleException $e) {
            Log::error('OpenAI API error', [
                'error' => $e->getMessage(),
                'response_id' => $responseId,
            ]);

            throw new \RuntimeException('Failed to cancel OpenAI response: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete a response.
     */
    public function deleteResponse(string $responseId): ResponseObject
    {
        try {
            $response = $this->client->delete("responses/{$responseId}");

            $data = json_decode($response->getBody()->getContents(), true);

            return ResponseObject::fromArray($data);
        } catch (GuzzleException $e) {
            Log::error('OpenAI API error', [
                'error' => $e->getMessage(),
                'response_id' => $responseId,
            ]);

            throw new \RuntimeException('Failed to delete OpenAI response: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Build the payload for API requests.
     *
     * @param  ResponseInput  $input
     * @param  ResponseOptions|null  $options
     * @return array<string, mixed>
     */
    protected function buildPayload(ResponseInput $input, ?ResponseOptions $options): array
    {
        $payload = [
            'model' => $options?->getModel() ?? $this->defaultModel,
            'input' => $input->toArray(),
        ];

        // Merge options if provided
        if ($options !== null) {
            $optionsArray = $options->toArray();
            
            // Map response_format to text.format per Responses API spec
            if (isset($optionsArray['response_format'])) {
                $payload['text'] = [
                    'format' => $optionsArray['response_format'],
                ];
                unset($optionsArray['response_format']);
            }

            $payload = array_merge($payload, $optionsArray);
        }

        return $payload;
    }
}
