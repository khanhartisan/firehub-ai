<?php

namespace App\Services\FactChecker;

use App\Contracts\FactChecker\FactChecker as FactCheckerContract;
use App\Contracts\OpenAI\OpenAIClient;
use App\Services\OpenAI\OpenAIManager;
use Illuminate\Support\Manager;

class FactCheckerManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('factchecker.default', 'basic');
    }

    protected function createBasicDriver(): FactCheckerContract
    {
        $config = $this->config->get('factchecker.drivers.basic', []);

        return new Drivers\BasicFactCheckerDriver($config);
    }

    protected function createOpenaiDriver(): FactCheckerContract
    {
        $config = $this->config->get('factchecker.drivers.openai', []);

        return new Drivers\OpenAIFactCheckerDriver(
            $this->container->make(OpenAIClient::class),
            $config
        );
    }

    protected function createOpenaiCompatibleDriver(): FactCheckerContract
    {
        $config = $this->config->get('factchecker.drivers.openai_compatible', []);

        return new Drivers\OpenAICompatibleFactCheckerDriver(
            $this->container->make(OpenAIManager::class),
            $config
        );
    }
}
