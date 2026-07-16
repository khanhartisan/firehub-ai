<?php

namespace App\Contracts;

interface Configurable
{
    public function setConfig(Config $config): static;

    public function getConfig(): ?Config;

    public function makeConfig(): ?Config;
}