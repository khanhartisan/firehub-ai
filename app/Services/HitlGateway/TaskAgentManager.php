<?php

namespace App\Services\HitlGateway;

use App\Contracts\HitlGateway\TaskAgent;
use App\Contracts\OpenAI\OpenAIClient;
use Illuminate\Support\Manager;

class TaskAgentManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('hitlgateway.task_agent', 'dummy');
    }

    protected function createDummyDriver(): TaskAgent
    {
        $config = $this->config->get('hitlgateway.task_agent_drivers.dummy', []);

        return new TaskAgentDrivers\DummyTaskAgent($config);
    }

    protected function createOpenaiDriver(): TaskAgent
    {
        $config = $this->config->get('hitlgateway.task_agent_drivers.openai', []);

        return new TaskAgentDrivers\OpenAITaskAgent(
            $this->container->make(OpenAIClient::class),
            $config
        );
    }

    protected function createOpenaiCompatibleDriver(): TaskAgent
    {
        $config = $this->config->get('hitlgateway.task_agent_drivers.openai_compatible', []);

        return new TaskAgentDrivers\OpenAICompatibleTaskAgent(
            $this->container->make('openai.manager'),
            $config
        );
    }
}
