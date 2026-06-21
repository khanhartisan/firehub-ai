<?php

namespace App\Services\Synthesizer\Tagger;

use App\Contracts\OpenAI\OpenAIClient;
use App\Services\Synthesizer\Support\SubserviceManager;
use App\Services\Synthesizer\Tagger\Drivers\BasicTaggerDriver;
use App\Services\Synthesizer\Tagger\Drivers\OpenAICompatibleTaggerDriver;
use App\Services\Synthesizer\Tagger\Drivers\OpenAITaggerDriver;

class TaggerManager extends SubserviceManager
{
    protected function configKey(): string
    {
        return 'tagger';
    }

    protected function createBasicDriver(): BasicTaggerDriver
    {
        return new BasicTaggerDriver;
    }

    protected function createOpenaiDriver(): OpenAITaggerDriver
    {
        return new OpenAITaggerDriver(
            $this->container->make(OpenAIClient::class),
            $this->driverConfiguration('openai'),
        );
    }

    protected function createOpenaiCompatibleDriver(): OpenAICompatibleTaggerDriver
    {
        return new OpenAICompatibleTaggerDriver(
            $this->driverConfiguration('openai_compatible'),
        );
    }
}
