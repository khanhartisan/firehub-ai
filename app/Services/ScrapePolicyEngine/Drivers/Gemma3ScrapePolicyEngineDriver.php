<?php

namespace App\Services\ScrapePolicyEngine\Drivers;

use App\Services\OpenAI\OpenAIManager;

class Gemma3ScrapePolicyEngineDriver extends OpenAIScrapePolicyEngineDriver
{
    public function __construct(OpenAIManager $openAIManager, array $config = [])
    {
        parent::__construct($openAIManager->driver('gemma3'), $config);
    }
}
