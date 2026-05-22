<?php

namespace App\Services\Synthesizer\Writer;

use App\Contracts\OpenAI\OpenAIClient;
use App\Services\Synthesizer\Support\SubserviceManager;
use App\Services\Synthesizer\Writer\Drivers\BasicWriterDriver;
use App\Services\Synthesizer\Writer\Drivers\OpenAICompatibleWriterDriver;
use App\Services\Synthesizer\Writer\Drivers\OpenAIWriterDriver;

class WriterManager extends SubserviceManager
{
    protected function configKey(): string
    {
        return 'writer';
    }

    protected function createBasicDriver(): BasicWriterDriver
    {
        return new BasicWriterDriver;
    }

    protected function createOpenaiDriver(): OpenAIWriterDriver
    {
        return new OpenAIWriterDriver(
            $this->container->make(OpenAIClient::class),
            $this->driverConfiguration('openai'),
        );
    }

    protected function createOpenaiCompatibleDriver(): OpenAICompatibleWriterDriver
    {
        return new OpenAICompatibleWriterDriver(
            $this->driverConfiguration('openai_compatible'),
        );
    }
}
