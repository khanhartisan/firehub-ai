<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAuditor;

use App\Contracts\OpenAI\OpenAIClient;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\BasicIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\OpenAICompatibleIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\OpenAIIdeaAuditorDriver;
use App\Services\Synthesizer\Support\SubserviceManager;

class IdeaAuditorManager extends SubserviceManager
{
    protected function configKey(): string
    {
        return 'idea_auditor';
    }

    protected function createBasicDriver(): BasicIdeaAuditorDriver
    {
        return new BasicIdeaAuditorDriver;
    }

    protected function createOpenaiDriver(): OpenAIIdeaAuditorDriver
    {
        return new OpenAIIdeaAuditorDriver(
            $this->container->make(OpenAIClient::class),
            $this->driverConfiguration('openai'),
        );
    }

    protected function createOpenaiCompatibleDriver(): OpenAICompatibleIdeaAuditorDriver
    {
        return new OpenAICompatibleIdeaAuditorDriver(
            $this->driverConfiguration('openai_compatible'),
        );
    }
}
