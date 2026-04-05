<?php

namespace App\Services\VerticalResolver\Drivers;

use App\Services\OpenAI\OpenAIManager;

class Gemma3VerticalResolverDriver extends OpenAIVerticalResolverDriver
{
    public function __construct(OpenAIManager $openAIManager, array $config = [])
    {
        parent::__construct($openAIManager->driver('gemma3'), $config);
    }
}
