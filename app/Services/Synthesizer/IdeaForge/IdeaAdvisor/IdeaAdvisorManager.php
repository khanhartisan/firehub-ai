<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAdvisor;

use App\Contracts\OpenAI\OpenAIClient;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\BasicIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\OpenAICompatibleIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\OpenAICompatibleIdeaExpansionAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\OpenAIIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\OpenAIIdeaExpansionAdvisorDriver;
use App\Services\Synthesizer\Support\SubserviceManager;

class IdeaAdvisorManager extends SubserviceManager
{
    protected function configKey(): string
    {
        return 'idea_advisor';
    }

    protected function createBasicDriver(): BasicIdeaAdvisorDriver
    {
        return new BasicIdeaAdvisorDriver;
    }

    protected function createOpenaiDriver(): OpenAIIdeaAdvisorDriver
    {
        return new OpenAIIdeaAdvisorDriver(
            $this->container->make(OpenAIClient::class),
            $this->driverConfiguration('openai'),
        );
    }

    protected function createOpenaiExpansionDriver(): OpenAIIdeaExpansionAdvisorDriver
    {
        return new OpenAIIdeaExpansionAdvisorDriver(
            $this->container->make(OpenAIClient::class),
            $this->driverConfiguration('openai'),
        );
    }

    protected function createOpenaiCompatibleDriver(): OpenAICompatibleIdeaAdvisorDriver
    {
        return new OpenAICompatibleIdeaAdvisorDriver(
            $this->driverConfiguration('openai_compatible'),
        );
    }

    protected function createOpenaiCompatibleExpansionDriver(): OpenAICompatibleIdeaExpansionAdvisorDriver
    {
        return new OpenAICompatibleIdeaExpansionAdvisorDriver(
            $this->driverConfiguration('openai_compatible'),
        );
    }
}
