<?php

namespace App\Services\HitlGateway;

use App\Contracts\HitlGateway\TaskAgent;
use Illuminate\Support\Manager;

class TaskAgentManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('hitlgateway.task_agent', 'dummy');
    }

    protected function createDummyDriver(): TaskAgent
    {
        $config = $this->config->get('hitlgateway.drivers.dummy', []);

        return new TaskAgentDrivers\DummyTaskAgent($config);
    }
}
