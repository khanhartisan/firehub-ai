<?php

namespace App\Services\Synthesizer\Illustration\Illustrator\Drivers;

use App\Contracts\Filesystem\File as FilesystemFile;
use App\Contracts\Synthesizer\Illustration\IllustrationContext;
use App\Contracts\Synthesizer\Illustration\IllustrationDirection;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use App\Enums\AspectRatio;
use App\Services\Synthesizer\Illustration\Illustrator\IllustratorService;
use Illuminate\Support\Facades\Storage;

class BasicIllustratorDriver extends IllustratorService
{
    // Minimal 1×1 transparent PNG used as a placeholder image.
    private const DUMMY_PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    public function __construct()
    {
        $this->setIdentifier('basic-illustrator');
        $this->setDescription('Basic deterministic illustrator driver for local/dev usage.');
    }

    public function generate(IllustrationContext $context, IllustrationDirection $direction): IllustrationResult
    {
        $result = new IllustrationResult;
        $result->setIllustrationContext($context);

        $aspectRatio = null;
        if (is_string($context->getAspectRatioValue())) {
            $aspectRatio = AspectRatio::tryFrom($context->getAspectRatioValue());
        }
        $aspectRatio ??= AspectRatio::FREE;
        $result->setAspectRatio($aspectRatio);

        $contextPayload = $context->toArray();
        unset($contextPayload['identifier']);

        $seedPayload = [
            'context' => $contextPayload,
            'direction' => $direction->toArray(),
        ];
        $seed = substr(sha1(json_encode($seedPayload) ?: ''), 0, 12);
        $result->setSeed($seed);

        $path = "illustrations/generated/basic-{$seed}-1.png";
        if (! Storage::exists($path)) {
            Storage::put($path, base64_decode(self::DUMMY_PNG_B64));
        }

        $result->addFile(new FilesystemFile()->setPath($path));

        return $result;
    }
}
