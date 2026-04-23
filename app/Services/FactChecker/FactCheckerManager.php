<?php

namespace App\Services\FactChecker;

use App\Contracts\FactChecker\FactChecker as FactCheckerContract;
use App\Contracts\OpenAI\OpenAIClient;
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
}
