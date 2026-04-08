<?php

namespace App\Contracts\Synthesizer;

interface Synthesizer
{
    public function setBriefBuilder(BriefBuilder $builder): static;

    public function getBriefBuilder(): BriefBuilder;

    public function setOutlineBuilder(OutlineBuilder $builder): static;

    public function getOutlineBuilder(): OutlineBuilder;

    public function setAuthor(Author $author): static;

    public function getAuthor(): Author;
}