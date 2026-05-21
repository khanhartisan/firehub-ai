<?php

namespace App\Services\VerticalResolver\Drivers;

use App\Services\OpenAI\OpenAIManager;

class OpenAICompatibleVerticalResolverDriver extends OpenAIVerticalResolverDriver
{
    public function __construct(OpenAIManager $openAIManager, array $config = [])
    {
        parent::__construct($openAIManager->driver('openai_compatible'), $config);
    }
}
