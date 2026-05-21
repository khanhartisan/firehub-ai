<?php

namespace App\Services\FileVision;

use App\Contracts\OpenAI\OpenAIClient;
use Illuminate\Support\Manager;

class FileVisionManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('filevision.default', 'basic');
    }

    /**
     * Create a basic driver instance.
     */
    protected function createBasicDriver(): Drivers\BasicFileVisionDriver
    {
        $config = $this->config->get('filevision.drivers.basic', []);

        return new Drivers\BasicFileVisionDriver($config);
    }

    /**
     * Create an OpenAI driver instance.
     */
    protected function createOpenaiDriver(): Drivers\OpenAIFileVisionDriver
    {
        $config = $this->config->get('filevision.drivers.openai', []);

        return new Drivers\OpenAIFileVisionDriver($this->container->make(OpenAIClient::class), $config);
    }

    /**
     * Create an OpenAI-compatible driver instance (uses OpenAI manager's openai_compatible backend).
     */
    protected function createOpenaiCompatibleDriver(): Drivers\OpenAICompatibleFileVisionDriver
    {
        $config = $this->config->get('filevision.drivers.openai_compatible', []);

        return new Drivers\OpenAICompatibleFileVisionDriver(
            $this->container->make('openai.manager'),
            $config
        );
    }
}
