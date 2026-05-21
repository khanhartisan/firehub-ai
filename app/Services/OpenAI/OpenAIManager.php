<?php

namespace App\Services\OpenAI;

use Illuminate\Support\Manager;

class OpenAIManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('openai.default', 'openai');
    }

    /**
     * Create an OpenAI driver instance.
     */
    protected function createOpenaiDriver(): Drivers\OpenAIDriver
    {
        $config = $this->config->get('openai.drivers.openai', []);

        return new Drivers\OpenAIDriver($config);
    }

    /**
     * Create an OpenAI-compatible API driver instance (any vendor exposing an OpenAI-style API).
     */
    protected function createOpenaiCompatibleDriver(): Drivers\OpenAICompatibleDriver
    {
        $config = $this->config->get('openai.drivers.openai_compatible', []);

        return new Drivers\OpenAICompatibleDriver($config);
    }
}
