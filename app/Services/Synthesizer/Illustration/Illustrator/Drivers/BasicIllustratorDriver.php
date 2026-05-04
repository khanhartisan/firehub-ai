<?php

namespace App\Services\Synthesizer\Illustration\Illustrator\Drivers;

use App\Contracts\Synthesizer\Illustration\IllustrationContext;
use App\Contracts\Synthesizer\Illustration\IllustrationDirection;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use App\Enums\AspectRatio;
use App\Services\Synthesizer\Illustration\Illustrator\IllustratorService;

class BasicIllustratorDriver extends IllustratorService
{
    public function __construct()
    {
        $this->setIdentifier('basic-illustrator');
        $this->setDescription('Basic deterministic illustrator driver for local/dev usage.');
    }

    public function generate(IllustrationContext $context, IllustrationDirection $direction): IllustrationResult
    {
        $result = new IllustrationResult();
        $result->setIllustrationContext($context);

        $aspectRatio = $context->getAspectRatioValue();
        if (is_string($aspectRatio)) {
            $result->setAspectRatio(AspectRatio::tryFrom($aspectRatio));
        }

        $seedPayload = [
            'context' => $context->toArray(),
            'direction' => $direction->toArray(),
        ];
        $result->setSeed(substr(sha1(json_encode($seedPayload) ?: ''), 0, 12));

        return $result;
    }
}

