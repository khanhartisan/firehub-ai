<?php

namespace App\Contracts\PlatformManager\FlyCms\Exceptions;

class FlyCmsException extends \Exception
{
    protected ?string $fullLogs = null;

    public function getFullLogs(): ?string
    {
        return $this->fullLogs;
    }

    public function setFullLogs(?string $fullLogs): static
    {
        $this->fullLogs = $fullLogs;
        return $this;
    }
}