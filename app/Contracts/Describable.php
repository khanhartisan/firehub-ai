<?php

namespace App\Contracts;

/**
 * Objects that have an optional text description (e.g. FileInformation, entities).
 */
interface Describable
{
    /** @return string|null The description text or null if not set */
    public function getDescription(): ?string;

    /** @param  string|null  $description  Description to set */
    public function setDescription(?string $description): static;
}