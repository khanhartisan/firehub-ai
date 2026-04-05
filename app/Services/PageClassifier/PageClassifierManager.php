<?php

namespace App\Services\PageClassifier;

use App\Contracts\OpenAI\OpenAIClient;
use Illuminate\Support\Manager;

class PageClassifierManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('pageclassifier.default', 'openai');
    }

    /**
     * Create an OpenAI driver instance.
     */
    protected function createOpenaiDriver(): Drivers\OpenAIPageClassifierDriver
    {
        $config = $this->config->get('pageclassifier.drivers.openai', []);

        return new Drivers\OpenAIPageClassifierDriver($this->container->make(OpenAIClient::class), $config);
    }

    /**
     * Create a Gemma 3 driver instance (uses OpenAI manager's gemma3 backend).
     */
    protected function createGemma3Driver(): Drivers\Gemma3PageClassifierDriver
    {
        $config = $this->config->get('pageclassifier.drivers.gemma3', []);

        return new Drivers\Gemma3PageClassifierDriver(
            $this->container->make('openai.manager'),
            $config
        );
    }
}
