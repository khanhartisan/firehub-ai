<?php

namespace App\Services\FactChecker;

use App\Contracts\FactChecker\FactChecker as FactCheckerContract;
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
}
