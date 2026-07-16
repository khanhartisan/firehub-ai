<?php

namespace App\Services\HitlGateway\TaskAgentDrivers;

use App\Services\OpenAI\OpenAIManager;

class OpenAICompatibleTaskAgent extends OpenAITaskAgent
{
    public function __construct(OpenAIManager $openAIManager, array $config = [])
    {
        parent::__construct($openAIManager->driver('openai_compatible'), $config);
    }
}
