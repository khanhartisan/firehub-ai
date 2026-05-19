<?php

namespace App\Services\Synthesizer\Illustration\Director;

use App\Contracts\OpenAI\OpenAIClient;
use App\Services\Synthesizer\Illustration\Director\Drivers\BasicDirectorDriver;
use App\Services\Synthesizer\Illustration\Director\Drivers\OpenAIDirectorDriver;
use App\Services\Synthesizer\Support\SubserviceManager;

class IllustrationDirectorManager extends SubserviceManager
{
    protected function configKey(): string
    {
        return 'illustration_director';
    }

    protected function createBasicDriver(): BasicDirectorDriver
    {
        return new BasicDirectorDriver;
    }

    protected function createOpenaiDriver(): OpenAIDirectorDriver
    {
        return new OpenAIDirectorDriver(
            $this->container->make(OpenAIClient::class),
            $this->driverConfiguration('openai'),
        );
    }
}
