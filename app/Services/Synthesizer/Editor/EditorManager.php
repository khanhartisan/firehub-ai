<?php

namespace App\Services\Synthesizer\Editor;

use App\Contracts\OpenAI\OpenAIClient;
use App\Services\Synthesizer\Editor\Drivers\BasicEditorDriver;
use App\Services\Synthesizer\Editor\Drivers\OpenAICompatibleEditorDriver;
use App\Services\Synthesizer\Editor\Drivers\OpenAIEditorDriver;
use App\Services\Synthesizer\Support\SubserviceManager;

class EditorManager extends SubserviceManager
{
    protected function configKey(): string
    {
        return 'editor';
    }

    protected function createBasicDriver(): BasicEditorDriver
    {
        return new BasicEditorDriver;
    }

    protected function createOpenaiDriver(): OpenAIEditorDriver
    {
        return new OpenAIEditorDriver(
            $this->container->make(OpenAIClient::class),
            $this->driverConfiguration('openai'),
        );
    }

    protected function createOpenaiCompatibleDriver(): OpenAICompatibleEditorDriver
    {
        return new OpenAICompatibleEditorDriver(
            $this->driverConfiguration('openai_compatible'),
        );
    }
}
