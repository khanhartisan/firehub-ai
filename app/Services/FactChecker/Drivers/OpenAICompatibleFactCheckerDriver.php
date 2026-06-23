<?php

namespace App\Services\FactChecker\Drivers;

use App\Services\OpenAI\OpenAIManager;

class OpenAICompatibleFactCheckerDriver extends OpenAIFactCheckerDriver
{
    public function __construct(OpenAIManager $openAIManager, array $config = [])
    {
        parent::__construct($openAIManager->driver('openai_compatible'), $config);
    }
}
