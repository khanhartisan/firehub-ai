<?php

namespace App\Services\Synthesizer\Illustration\Illustrator\Drivers;

use App\Enums\AspectRatio;
use App\Services\Synthesizer\Support\SynthesizerOpenAICompatibleClient;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;
use App\Utils\Debugger;
use App\Utils\Str;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class OpenAICompatibleIllustratorDriver extends OpenAIIllustratorDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $merged = SynthesizerSubserviceConfig::driver('illustrator', 'openai_compatible', $config);

        parent::__construct(null, $merged);

        $this->setIdentifier((string) ($merged['identifier'] ?? 'openai-compatible-illustrator'));
        $this->setDescription((string) ($merged['description'] ?? 'OpenAI-compatible illustration generator.'));
    }

    /** @return array{files: array<int, string>, seed: string|null} */
    protected function generateImages(string $prompt, AspectRatio $aspectRatio): array
    {
        $http = SynthesizerOpenAICompatibleClient::connectionConfig('illustrator', $this->config);
        $apiKey = (string) ($http['api_key'] ?? '');
        if ($apiKey === '') {
            throw new RuntimeException('Failed to generate illustration image: missing OPENAI_COMPATIBLE_API_KEY.');
        }

        $baseUrl = rtrim((string) ($http['base_url'] ?? 'https://api.openai.com/v1/'), '/').'/';
        $client = new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ],
            'timeout' => (int) ($http['timeout'] ?? 120),
        ]);

        $payload = [
            'model' => $this->getModel(),
            'prompt' => $prompt,
            'size' => $this->resolveImageSize($aspectRatio),
            'quality' => $this->getQuality(),
            'n' => $this->getCount(),
            'output_format' => $this->getOutputFormat(),
        ];

        Debugger::devConsoleDump('
            -- Sending image generation request to: '.$baseUrl.'images/generations
            / Payload: '.json_encode($payload).'
        ');

        try {
            $response = $client->post('images/generations', ['json' => $payload]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Failed to generate illustration image: '.$e->getMessage(), 0, $e);
        }

        return $this->parseImageGenerationResponse($response);
    }

    /** @return array{files: array<int, string>, seed: string|null} */
    protected function parseImageGenerationResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $contentType = strtolower($response->getHeaderLine('Content-Type'));

        if ($this->isDirectImageResponse($contentType, $body)) {
            return [
                'files' => [$this->persistGeneratedImage($body, 1, $contentType)],
                'seed' => null,
            ];
        }

        $data = json_decode($body, true);
        if (! is_array($data)) {
            throw new RuntimeException('Failed to generate illustration image: invalid JSON payload.');
        }

        if (isset($data['error'])) {
            $error = $data['error'];
            $message = is_array($error)
                ? (string) ($error['message'] ?? json_encode($error, JSON_UNESCAPED_UNICODE))
                : (string) $error;

            throw new RuntimeException('Failed to generate illustration image: '.$message);
        }

        $rows = $data['data'] ?? null;
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('Failed to generate illustration image: no image returned.');
        }

        $paths = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $b64 = $row['b64_json'] ?? null;
            if (! is_string($b64) || $b64 === '') {
                continue;
            }

            $binary = base64_decode($b64, true);
            if ($binary === false) {
                continue;
            }

            $paths[] = $this->persistGeneratedImage($binary, $index + 1);
        }

        if ($paths === []) {
            throw new RuntimeException('Failed to generate illustration image: unable to decode image bytes.');
        }

        return [
            'files' => $paths,
            'seed' => is_scalar($data['seed'] ?? null) ? (string) $data['seed'] : null,
        ];
    }

    protected function isDirectImageResponse(string $contentType, string $body): bool
    {
        if (str_contains($contentType, 'image/')) {
            return $body !== '';
        }

        if (str_contains($contentType, 'application/json') || str_contains($contentType, 'text/json')) {
            return false;
        }

        if ($body === '') {
            return false;
        }

        $data = json_decode($body, true);
        if (is_array($data) && array_key_exists('data', $data)) {
            return false;
        }

        return $this->looksLikeImageBinary($body);
    }

    protected function looksLikeImageBinary(string $body): bool
    {
        return str_starts_with($body, "\x89PNG\r\n\x1a\n")
            || str_starts_with($body, "\xFF\xD8\xFF")
            || str_starts_with($body, 'GIF87a')
            || str_starts_with($body, 'GIF89a')
            || (str_starts_with($body, 'RIFF') && strlen($body) >= 12 && substr($body, 8, 4) === 'WEBP');
    }

    protected function persistGeneratedImage(string $binary, int $index, ?string $contentType = null): string
    {
        $ext = $this->resolveImageExtension($contentType);
        $path = sprintf(
            '%s/%s-%d.%s',
            $this->getStorageDirectory(),
            Str::ulid(),
            $index,
            $ext
        );

        $saved = Storage::disk($this->getStorageDisk())->put($path, $binary);
        if (! $saved) {
            throw new RuntimeException('Failed to persist generated illustration image to filesystem.');
        }

        return $path;
    }

    protected function resolveImageExtension(?string $contentType = null): string
    {
        $contentType = strtolower((string) $contentType);

        if (str_contains($contentType, 'jpeg') || str_contains($contentType, 'jpg')) {
            return 'jpg';
        }

        if (str_contains($contentType, 'webp')) {
            return 'webp';
        }

        if (str_contains($contentType, 'gif')) {
            return 'gif';
        }

        if (str_contains($contentType, 'png')) {
            return 'png';
        }

        $configured = strtolower($this->getOutputFormat());

        return $configured === 'jpeg' ? 'jpg' : $configured;
    }
}
