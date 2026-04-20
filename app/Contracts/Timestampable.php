<?php

namespace App\Contracts;

use Carbon\CarbonInterface;

interface Timestampable
{
    public function getCreatedAt(): ?CarbonInterface;

    public function setCreatedAt(?CarbonInterface $carbon): static;

    public function getUpdatedAt(): ?CarbonInterface;

    public function setUpdatedAt(?CarbonInterface $carbon): static;
}