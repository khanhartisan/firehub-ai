<?php

namespace App\Services\PageClassifier\Drivers;

use App\Services\OpenAI\OpenAIManager;

class OpenAICompatiblePageClassifierDriver extends OpenAIPageClassifierDriver
{
    public function __construct(OpenAIManager $openAIManager, array $config = [])
    {
        parent::__construct($openAIManager->driver('openai_compatible'), $config);
    }
}
