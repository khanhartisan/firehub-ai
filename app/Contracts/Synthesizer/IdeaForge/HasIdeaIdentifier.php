<?php

namespace App\Contracts\Synthesizer\IdeaForge;

trait HasIdeaIdentifier
{
    /** Which {@see Idea} this report refers to (same as {@see Idea::getIdentifier()}). */
    protected ?string $ideaIdentifier = null;

    public function getIdeaIdentifier(): ?string
    {
        return $this->ideaIdentifier;
    }

    public function setIdeaIdentifier(?string $ideaIdentifier): static
    {
        $this->ideaIdentifier = $ideaIdentifier !== null ? trim($ideaIdentifier) : null;
        if ($this->ideaIdentifier === '') {
            $this->ideaIdentifier = null;
        }

        return $this;
    }
}
