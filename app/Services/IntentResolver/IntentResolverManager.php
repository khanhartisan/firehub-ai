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
     * Create a Gemma 3 driver instance (uses OpenAI manager's gemma3 backend).
     */
    protected function createGemma3Driver(): Drivers\Gemma3IntentResolverDriver
    {
        $config = $this->config->get('intentresolver.drivers.gemma3', []);

        return new Drivers\Gemma3IntentResolverDriver(
            $this->container->make('openai.manager'),
            $config
        );
    }
}
