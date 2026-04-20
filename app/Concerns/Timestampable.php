<?php

namespace App\Concerns;

use Carbon\CarbonInterface;

trait Timestampable
{
    protected ?CarbonInterface $createdAt = null;

    protected ?CarbonInterface $updatedAt = null;

    public function getCreatedAt(): ?CarbonInterface
    {
        return $this->createdAt ??= now();
    }

    public function setCreatedAt(?CarbonInterface $carbon): static
    {
        $this->createdAt = $carbon;
        return $this;
    }

    public function getUpdatedAt(): ?CarbonInterface
    {
        return $this->updatedAt ??= now();
    }

    public function setUpdatedAt(?CarbonInterface $carbon): static
    {
        $this->updatedAt = $carbon;
        return $this;
    }
}