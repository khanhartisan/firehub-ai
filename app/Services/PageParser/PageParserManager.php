<?php

namespace App\Services\PageParser;

use App\Contracts\OpenAI\OpenAIClient;
use Illuminate\Support\Manager;

class PageParserManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('pageparser.default', 'openai');
    }

    /**
     * Create an OpenAI driver instance.
     */
    protected function createOpenaiDriver(): Drivers\OpenAIPageParserDriver
    {
        $config = $this->config->get('pageparser.drivers.openai', []);

        return new Drivers\OpenAIPageParserDriver($this->container->make(OpenAIClient::class), $config);
    }

    /**
     * Create an OpenAI-compatible driver instance (uses OpenAI manager's openai_compatible backend).
     */
    protected function createOpenaiCompatibleDriver(): Drivers\OpenAICompatiblePageParserDriver
    {
        $config = $this->config->get('pageparser.drivers.openai_compatible', []);

        return new Drivers\OpenAICompatiblePageParserDriver(
            $this->container->make('openai.manager'),
            $config
        );
    }
}
