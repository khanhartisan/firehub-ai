<?php

namespace App\Services\Synthesizer\OutlineBuilder;

use App\Contracts\OpenAI\OpenAIClient;
use App\Services\Synthesizer\OutlineBuilder\Drivers\BasicOutlineBuilderDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\OpenAIOutlineBuilderDriver;
use App\Services\Synthesizer\Support\SubserviceManager;

class OutlineBuilderManager extends SubserviceManager
{
    protected function configKey(): string
    {
        return 'outline_builder';
    }

    protected function createBasicDriver(): BasicOutlineBuilderDriver
    {
        return new BasicOutlineBuilderDriver;
    }

    protected function createOpenaiDriver(): OpenAIOutlineBuilderDriver
    {
        return new OpenAIOutlineBuilderDriver(
            $this->container->make(OpenAIClient::class),
            $this->driverConfiguration('openai'),
        );
    }
}
