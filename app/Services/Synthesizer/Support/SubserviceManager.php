<?php

namespace App\Services\Synthesizer\Support;

use Illuminate\Support\Manager;

abstract class SubserviceManager extends Manager
{
    abstract protected function configKey(): string;

    public function getDefaultDriver(): string
    {
        return (string) $this->config->get("synthesizer.{$this->configKey()}.default", 'basic');
    }

    /**
     * @return array<string, mixed>
     */
    protected function driverConfiguration(?string $driver = null): array
    {
        $driver ??= $this->getDefaultDriver();
        $config = $this->config->get("synthesizer.{$this->configKey()}.drivers.{$driver}", []);

        return is_array($config) ? $config : [];
    }
}
