<?php

namespace App\Contracts\CommonData\Concerns;

trait HasRationale
{
    /**
     * The underlying logic or strategic reason
     *
     * @var string|null
     */
    protected ?string $rationale = null;

    public function getRationale(): ?string
    {
        return $this->rationale;
    }

    public function setRationale(?string $rationale): static
    {
        $this->rationale = $rationale;

        return $this;
    }
}