<?php

namespace App\Services\FileVision\Drivers;

use App\Services\OpenAI\OpenAIManager;

class Gemma3FileVisionDriver extends OpenAIFileVisionDriver
{
    public function __construct(OpenAIManager $openAIManager, array $config = [])
    {
        parent::__construct($openAIManager->driver('gemma3'), $config);
    }
}
