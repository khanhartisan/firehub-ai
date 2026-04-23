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

    public function hydrateVerification(array $data): static
    {
        if (array_key_exists('verification', $data)) {
            if ($data['verification'] instanceof Verification) {
                $this->setVerification($data['verification']);
            } elseif (is_array($data['verification'])) {
                $this->setVerification(Verification::fromArray($data['verification']));
            } elseif ($data['verification'] === null) {
                $this->setVerification(null);
            }
        }

        return $this;
    }
}