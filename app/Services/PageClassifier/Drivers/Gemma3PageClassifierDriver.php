<?php

namespace App\Services\PageClassifier\Drivers;

use App\Services\OpenAI\OpenAIManager;

class Gemma3PageClassifierDriver extends OpenAIPageClassifierDriver
{
    public function __construct(OpenAIManager $openAIManager, array $config = [])
    {
        parent::__construct($openAIManager->driver('gemma3'), $config);
    }
}
