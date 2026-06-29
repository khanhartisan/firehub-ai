<?php

namespace App\Contracts\PlatformManager\FlyCms\Exceptions;

class FlyCmsException extends \Exception
{
    protected ?string $fullApiLogs = null;

    public function getFullApiLogs(): ?string
    {
        return $this->fullApiLogs;
    }

    public function setFullApiLogs(?string $fullApiLogs): static
    {
        $this->fullApiLogs = $fullApiLogs;
        return $this;
    }
}