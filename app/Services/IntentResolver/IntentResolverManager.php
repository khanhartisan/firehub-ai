<?php

namespace App\Services\IntentResolver;

use App\Contracts\OpenAI\OpenAIClient;
use Illuminate\Support\Manager;

class IntentResolverManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('intentresolver.default', 'openai');
    }

    /**
     * Create the OpenAI driver instance.
     */
    protected function createOpenaiDriver(): Drivers\OpenAIIntentResolverDriver
    {
        $config = $this->config->get('intentresolver.drivers.openai', []);

        return new Drivers\OpenAIIntentResolverDriver(
            $this->container->make(OpenAIClient::class),
            $config
        );
    }

    /**
     * Create an OpenAI-compatible driver instance (uses OpenAI manager's openai_compatible backend).
     */
    protected function createOpenaiCompatibleDriver(): Drivers\OpenAICompatibleIntentResolverDriver
    {
        $config = $this->config->get('intentresolver.drivers.openai_compatible', []);

        return new Drivers\OpenAICompatibleIntentResolverDriver(
            $this->container->make('openai.manager'),
            $config
        );
    }
}
