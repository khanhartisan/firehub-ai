<?php

namespace App\Services\Synthesizer\Illustration\Illustrator;

use App\Contracts\OpenAI\OpenAIClient;
use App\Services\Synthesizer\Illustration\Illustrator\Drivers\BasicIllustratorDriver;
use App\Services\Synthesizer\Illustration\Illustrator\Drivers\OpenAIDebugIllustratorDriver;
use App\Services\Synthesizer\Illustration\Illustrator\Drivers\OpenAICompatibleIllustratorDriver;
use App\Services\Synthesizer\Illustration\Illustrator\Drivers\OpenAIIllustratorDriver;
use App\Services\Synthesizer\Support\SubserviceManager;

class IllustratorManager extends SubserviceManager
{
    protected function configKey(): string
    {
        return 'illustrator';
    }

    protected function createBasicDriver(): BasicIllustratorDriver
    {
        return new BasicIllustratorDriver;
    }

    protected function createOpenaiDriver(): OpenAIIllustratorDriver
    {
        return new OpenAIIllustratorDriver(
            $this->container->make(OpenAIClient::class),
            $this->driverConfiguration('openai'),
        );
    }

    protected function createOpenaiCompatibleDriver(): OpenAICompatibleIllustratorDriver
    {
        return new OpenAICompatibleIllustratorDriver(
            $this->driverConfiguration('openai_compatible'),
        );
    }

    protected function createDebugDriver(): OpenAIDebugIllustratorDriver
    {
        return new OpenAIDebugIllustratorDriver(
            $this->container->make(OpenAIClient::class),
            $this->driverConfiguration('debug'),
        );
    }
}
