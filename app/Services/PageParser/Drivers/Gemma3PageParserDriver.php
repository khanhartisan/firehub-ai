<?php

namespace App\Services\PageParser\Drivers;

use App\Services\OpenAI\OpenAIManager;

class Gemma3PageParserDriver extends OpenAIPageParserDriver
{
    public function __construct(OpenAIManager $openAIManager, array $config = [])
    {
        parent::__construct($openAIManager->driver('gemma3'), $config);
    }
}
