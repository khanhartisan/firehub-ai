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
            $data = json_decode((string) $response->getBody(), true);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Failed to generate illustration image: '.$e->getMessage(), 0, $e);
        }

        if (! is_array($data)) {
            throw new RuntimeException('Failed to generate illustration image: invalid JSON payload.');
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

            $ext = strtolower($this->getOutputFormat()) === 'jpeg' ? 'jpg' : strtolower($this->getOutputFormat());
            $path = sprintf(
                '%s/%s-%d.%s',
                $this->getStorageDirectory(),
                Str::ulid(),
                $index + 1,
                $ext
            );

            $saved = Storage::disk($this->getStorageDisk())->put($path, $binary);
            if (! $saved) {
                throw new RuntimeException('Failed to persist generated illustration image to filesystem.');
            }

            $paths[] = $path;
        }

        if ($paths === []) {
            throw new RuntimeException('Failed to generate illustration image: unable to decode image bytes.');
        }

        return [
            'files' => $paths,
            'seed' => is_scalar($data['seed'] ?? null) ? (string) $data['seed'] : null,
        ];
    }
}
