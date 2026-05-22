<?php

namespace App\Services\Synthesizer\BriefBuilder;

use App\Contracts\OpenAI\OpenAIClient;
use App\Services\Synthesizer\BriefBuilder\Drivers\BasicBriefBuilderDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\OpenAICompatibleBriefBuilderDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\OpenAIBriefBuilderDriver;
use App\Services\Synthesizer\Support\SubserviceManager;

class BriefBuilderManager extends SubserviceManager
{
    protected function configKey(): string
    {
        return 'brief_builder';
    }

    protected function createBasicDriver(): BasicBriefBuilderDriver
    {
        return new BasicBriefBuilderDriver;
    }

    protected function createOpenaiDriver(): OpenAIBriefBuilderDriver
    {
        return new OpenAIBriefBuilderDriver(
            $this->container->make(OpenAIClient::class),
            $this->driverConfiguration('openai'),
        );
    }

    protected function createOpenaiCompatibleDriver(): OpenAICompatibleBriefBuilderDriver
    {
        return new OpenAICompatibleBriefBuilderDriver(
            $this->driverConfiguration('openai_compatible'),
        );
    }
}
