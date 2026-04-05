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
     * Create a Gemma 3 driver instance (uses OpenAI manager's gemma3 backend).
     */
    protected function createGemma3Driver(): Drivers\Gemma3PageParserDriver
    {
        $config = $this->config->get('pageparser.drivers.gemma3', []);

        return new Drivers\Gemma3PageParserDriver(
            $this->container->make('openai.manager'),
            $config
        );
    }
}
