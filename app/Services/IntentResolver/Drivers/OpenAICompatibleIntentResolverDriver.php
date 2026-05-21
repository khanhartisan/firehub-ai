<?php

namespace App\Services\IntentResolver\Drivers;

use App\Services\OpenAI\OpenAIManager;

class OpenAICompatibleIntentResolverDriver extends OpenAIIntentResolverDriver
{
    public function __construct(OpenAIManager $openAIManager, array $config = [])
    {
        parent::__construct($openAIManager->driver('openai_compatible'), $config);
    }
}
