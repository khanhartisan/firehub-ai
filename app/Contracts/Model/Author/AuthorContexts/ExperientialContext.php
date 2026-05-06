<?php

namespace App\Contracts\Model\Author\AuthorContexts;

use App\Contracts\CommonData\SemanticContext;

class ExperientialContext extends SemanticContext
{
    public function setBackstoryAnchors(array $backstoryAnchors, ?float $weight = null): static
    {
        return $this->set(
            'backstory_anchors',
            'Core life events or trauma shaping the author\'s worldview. Author may reference these as backstory to build credibility and explain their biases.',
            $backstoryAnchors,
            $weight
        );
    }

    public function setPopCultures(array $popCultures, ?float $weight = null): static
    {
        return $this->set(
            'pop_cultures',
            'Media, art, or entertainment the author consumes and uses for metaphors.',
            $popCultures,
            $weight
        );
    }

    public function setAnecdoteSeeds(array $anecdoteSeeds, ?float $weight = null): static
    {
        return $this->set(
            'anecdote_seeds',
            'Starting points hallucinate consistent, personal stories to illustrate a point, making the text feel lived-in.',
            $anecdoteSeeds,
            $weight
        );
    }
}