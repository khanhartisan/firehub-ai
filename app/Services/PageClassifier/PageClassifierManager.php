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
     * Create an OpenAI-compatible driver instance (uses OpenAI manager's openai_compatible backend).
     */
    protected function createOpenaiCompatibleDriver(): Drivers\OpenAICompatiblePageClassifierDriver
    {
        $config = $this->config->get('pageclassifier.drivers.openai_compatible', []);

        return new Drivers\OpenAICompatiblePageClassifierDriver(
            $this->container->make('openai.manager'),
            $config
        );
    }
}
