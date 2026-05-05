<?php

namespace App\Services\Synthesizer\Illustration\Illustrator\Drivers;

use App\Contracts\Filesystem\File as FilesystemFile;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\Synthesizer\Illustration\IllustrationContext;
use App\Contracts\Synthesizer\Illustration\IllustrationDirection;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use App\Enums\AspectRatio;
use App\Services\Synthesizer\Illustration\Illustrator\IllustratorService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Storage;
use App\Utils\Str;
use RuntimeException;

class OpenAIIllustratorDriver extends IllustratorService
{
    protected ?OpenAIClient $openAIClient;

    /** @var array<string, mixed> */
    protected array $config;

    /** @param array<string, mixed> $config */
    public function __construct(?OpenAIClient $openAIClient = null, array $config = [])
    {
        $this->openAIClient = $openAIClient;
        $this->config = array_merge(config('synthesizer.openai_illustrator', []), $config);

        $this->setIdentifier((string) ($this->config['identifier'] ?? 'openai-illustrator'));
        $this->setDescription((string) ($this->config['description'] ?? 'OpenAI-backed illustration generator.'));
    }

    public function generate(IllustrationContext $context, IllustrationDirection $direction): IllustrationResult
    {
        $aspectRatio = null;
        if (is_string($context->getAspectRatioValue())) {
            $aspectRatio = AspectRatio::tryFrom($context->getAspectRatioValue());
        }
        $aspectRatio ??= AspectRatio::FREE;

        $prompt = $this->buildPrompt($context, $direction);
        $responseData = $this->generateImages($prompt, $aspectRatio);

        $result = new IllustrationResult();
        $result->setIllustrationContext($context);
        $result->setAspectRatio($aspectRatio);

        $seed = trim((string) ($responseData['seed'] ?? ''));
        if ($seed !== '') {
            $result->setSeed($seed);
        }

        foreach (($responseData['files'] ?? []) as $path) {
            $path = trim((string) $path);
            if ($path !== '') {
                $result->addFile((new FilesystemFile())->setPath($path));
            }
        }

        return $result;
    }

    protected function getModel(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-image-1');
    }

    protected function getQuality(): string
    {
        return (string) ($this->config['quality'] ?? 'low');
    }

    protected function getOutputFormat(): string
    {
        return (string) ($this->config['output_format'] ?? 'png');
    }

    protected function getCount(): int
    {
        return max(1, min(4, (int) ($this->config['count'] ?? 1)));
    }

    protected function getStorageDisk(): string
    {
        return (string) ($this->config['filesystem_disk'] ?? 'public');
    }

    protected function getStorageDirectory(): string
    {
        return trim((string) ($this->config['filesystem_directory'] ?? 'illustrations/generated'), '/');
    }

    protected function buildPrompt(IllustrationContext $context, IllustrationDirection $direction): string
    {
        $json = json_encode([
            'context' => $context->toArray(),
            'direction' => $direction->toArray(),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return <<<PROMPT
Create a high-quality illustration based on the following structured direction.
Respect constraints and avoid adding unrelated visual elements.
If context and direction conflict, prioritize direction.
Ground all visual details in the provided JSON only.
Do not invent facts, domain knowledge, numbers, labels, names, or relationships that are not explicitly present.
Treat constraints and knowledge_guidelines as strict requirements and depict them faithfully.
When information is missing, remain generic rather than guessing specific facts.

Input JSON:
{$json}
PROMPT;
    }

    protected function resolveImageSize(AspectRatio $aspectRatio): string
    {
        if (in_array($aspectRatio, [AspectRatio::PORTRAIT_STANDARD, AspectRatio::PORTRAIT_TALL], true)) {
            return '1024x1536';
        }

        if (in_array(
            $aspectRatio,
            [
                AspectRatio::LANDSCAPE_STANDARD,
                AspectRatio::LANDSCAPE_WIDE,
                AspectRatio::CLASSIC_FILM,
                AspectRatio::CINEMATIC,
            ],
            true
        )) {
            return '1536x1024';
        }

        return '1024x1024';
    }

    /** @return array{files: array<int, string>, seed: string|null} */
    protected function generateImages(string $prompt, AspectRatio $aspectRatio): array
    {
        $openaiConfig = config('openai.drivers.openai', []);
        $apiKey = (string) ($openaiConfig['api_key'] ?? '');
        if ($apiKey === '') {
            throw new RuntimeException('Failed to generate illustration image with OpenAI: missing OPENAI_API_KEY.');
        }

        $baseUrl = rtrim((string) ($openaiConfig['base_url'] ?? 'https://api.openai.com/v1/'), '/').'/';
        $client = new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ],
            'timeout' => (int) ($openaiConfig['timeout'] ?? 120),
        ]);

        $payload = [
            'model' => $this->getModel(),
            'prompt' => $prompt,
            'size' => $this->resolveImageSize($aspectRatio),
            'quality' => $this->getQuality(),
            'n' => $this->getCount(),
            'output_format' => $this->getOutputFormat(),
        ];

        try {
            $response = $client->post('images/generations', ['json' => $payload]);
            $data = json_decode((string) $response->getBody(), true);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Failed to generate illustration image with OpenAI: '.$e->getMessage(), 0, $e);
        }

        if (! is_array($data)) {
            throw new RuntimeException('Failed to generate illustration image with OpenAI: invalid JSON payload.');
        }

        $rows = $data['data'] ?? null;
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('Failed to generate illustration image with OpenAI: no image returned.');
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
            throw new RuntimeException('Failed to generate illustration image with OpenAI: unable to decode image bytes.');
        }

        return [
            'files' => $paths,
            'seed' => is_scalar($data['seed'] ?? null) ? (string) $data['seed'] : null,
        ];
    }
}

