<?php

namespace App\Services\FileVision\Drivers;

use App\Services\OpenAI\OpenAIManager;

class OpenAICompatibleFileVisionDriver extends OpenAIFileVisionDriver
{
    public function __construct(OpenAIManager $openAIManager, array $config = [])
    {
        parent::__construct($openAIManager->driver('openai_compatible'), $config);
    }
}
