<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaPicker;

use App\Contracts\OpenAI\OpenAIClient;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\BasicIdeaPickerDriver;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\OpenAIIdeaPickerDriver;
use App\Services\Synthesizer\Support\SubserviceManager;

class IdeaPickerManager extends SubserviceManager
{
    protected function configKey(): string
    {
        return 'idea_picker';
    }

    protected function createBasicDriver(): BasicIdeaPickerDriver
    {
        return new BasicIdeaPickerDriver;
    }

    protected function createOpenaiDriver(): OpenAIIdeaPickerDriver
    {
        return new OpenAIIdeaPickerDriver(
            $this->container->make(OpenAIClient::class),
            $this->driverConfiguration('openai'),
        );
    }
}
