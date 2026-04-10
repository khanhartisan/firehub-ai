<?php

namespace App\Services\IntentResolver\Drivers;

use App\Services\OpenAI\OpenAIManager;

class Gemma3IntentResolverDriver extends OpenAIIntentResolverDriver
{
    public function __construct(OpenAIManager $openAIManager, array $config = [])
    {
        parent::__construct($openAIManager->driver('gemma3'), $config);
    }
}
