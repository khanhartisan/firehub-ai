<?php

namespace App\Contracts\CommonData\Concerns;

use App\Contracts\CommonData\Verification;

trait HasVerification
{
    protected ?Verification $verification = null;

    public function getVerification(): ?Verification
    {
        return $this->verification;
    }

    public function setVerification(?Verification $verification): static
    {
        $this->verification = $verification;

        return $this;
    }
}