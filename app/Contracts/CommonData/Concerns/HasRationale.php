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

    public function hydrateRationale(array $data): static
    {
        if (array_key_exists('rationale', $data)) {
            $this->setRationale($data['rationale'] !== null ? (string) $data['rationale'] : null);
        }

        return $this;
    }
}