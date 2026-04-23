<?php

namespace App\Contracts\Synthesizer\Researcher\Concerns;

use App\Contracts\Synthesizer\IdeaForge\Idea;

trait HasIdea
{
    protected Idea $idea;

    public function getIdea(): Idea
    {
        return $this->idea;
    }

    public function setIdea(Idea $idea): static
    {
        $this->idea = $idea;

        return $this;
    }
}