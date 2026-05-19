<?php

namespace App\Services\Synthesizer\Illustration\Illustrator\Drivers;

use App\Contracts\OpenAI\OpenAIClient;
use App\Enums\AspectRatio;
use App\Utils\Str;
use Illuminate\Support\Facades\File as LocalFile;
use Illuminate\Support\Facades\Storage;

class OpenAIDebugIllustratorDriver extends OpenAIIllustratorDriver
{
    // Minimal 1x1 transparent PNG used as a placeholder image.
    private const DUMMY_PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    /** @param array<string, mixed> $config */
    public function __construct(?OpenAIClient $openAIClient = null, array $config = [])
    {
        parent::__construct($openAIClient, $config);
    }

    /** @return array{files: array<int, string>, seed: string|null} */
    protected function generateImages(string $prompt, AspectRatio $aspectRatio): array
    {
        $seed = substr(sha1($prompt.'|'.$aspectRatio->value), 0, 12);
        $this->logPrompt($prompt, $aspectRatio, $seed);

        $ext = strtolower($this->getOutputFormat()) === 'jpeg' ? 'jpg' : strtolower($this->getOutputFormat());
        $path = sprintf('%s/debug-%s-1.%s', $this->getStorageDirectory(), Str::ulid(), $ext);
        Storage::disk($this->getStorageDisk())->put($path, base64_decode(self::DUMMY_PNG_B64));

        return [
            'files' => [$path],
            'seed' => $seed,
        ];
    }

    protected function logPrompt(string $prompt, AspectRatio $aspectRatio, string $seed): void
    {
        $logPath = (string) ($this->config['debug_log_path'] ?? storage_path('logs/openai-illustrator-debug.log'));
        $payload = [
            'timestamp' => now()->toIso8601String(),
            'driver' => static::class,
            'model' => $this->getModel(),
            'quality' => $this->getQuality(),
            'output_format' => $this->getOutputFormat(),
            'count' => $this->getCount(),
            'resolved_image_size' => $this->resolveImageSize($aspectRatio),
            'aspect_ratio' => $aspectRatio->value,
            'seed' => $seed,
            'prompt' => $prompt,
        ];

        LocalFile::ensureDirectoryExists(dirname($logPath));
        LocalFile::append(
            $logPath,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL
        );
    }
}
