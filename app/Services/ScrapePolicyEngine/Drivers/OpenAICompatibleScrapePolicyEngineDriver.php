<?php

namespace App\Services\ScrapePolicyEngine\Drivers;

use App\Services\OpenAI\OpenAIManager;

class OpenAICompatibleScrapePolicyEngineDriver extends OpenAIScrapePolicyEngineDriver
{
    public function __construct(OpenAIManager $openAIManager, array $config = [])
    {
        parent::__construct($openAIManager->driver('openai_compatible'), $config);
    }
}
