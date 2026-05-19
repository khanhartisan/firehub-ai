<?php

namespace App\Services\Synthesizer\Researcher;

use App\Contracts\OpenAI\OpenAIClient;
use App\Services\Synthesizer\Researcher\Drivers\BasicResearcherDriver;
use App\Services\Synthesizer\Researcher\Drivers\OpenAIResearcherDriver;
use App\Services\Synthesizer\Support\SubserviceManager;

class ResearcherManager extends SubserviceManager
{
    protected function configKey(): string
    {
        return 'researcher';
    }

    protected function createBasicDriver(): BasicResearcherDriver
    {
        return new BasicResearcherDriver;
    }

    protected function createOpenaiDriver(): OpenAIResearcherDriver
    {
        return new OpenAIResearcherDriver(
            $this->container->make(OpenAIClient::class),
            $this->driverConfiguration('openai'),
        );
    }
}
