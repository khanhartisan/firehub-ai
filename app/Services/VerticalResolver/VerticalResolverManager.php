<?php

namespace App\Services\VerticalResolver;

use Illuminate\Support\Manager;

class VerticalResolverManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('verticalresolver.default', 'openai');
    }

    /**
     * Create the keyword driver instance.
     */
    protected function createKeywordDriver(): Drivers\KeywordVerticalResolverDriver
    {
        $config = $this->config->get('verticalresolver.drivers.keyword', []);

        return new Drivers\KeywordVerticalResolverDriver($config);
    }

    /**
     * Create the OpenAI driver instance.
     */
    protected function createOpenaiDriver(): Drivers\OpenAIVerticalResolverDriver
    {
        $config = $this->config->get('verticalresolver.drivers.openai', []);

        return new Drivers\OpenAIVerticalResolverDriver(
            $this->container->make(\App\Contracts\OpenAI\OpenAIClient::class),
            $config
        );
    }

    /**
     * Create an OpenAI-compatible driver instance (uses OpenAI manager's openai_compatible backend).
     */
    protected function createOpenaiCompatibleDriver(): Drivers\OpenAICompatibleVerticalResolverDriver
    {
        $config = $this->config->get('verticalresolver.drivers.openai_compatible', []);

        return new Drivers\OpenAICompatibleVerticalResolverDriver(
            $this->container->make('openai.manager'),
            $config
        );
    }
}
