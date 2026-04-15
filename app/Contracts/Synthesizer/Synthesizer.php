<?php

namespace App\Contracts\Synthesizer;

use App\Contracts\Synthesizer\Author\Author;
use App\Contracts\Synthesizer\BriefBuilder\BriefBuilder;
use App\Contracts\Synthesizer\IdeaForge\IdeaForge;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineBuilder;

interface Synthesizer
{
    public function getIdeaForge(): IdeaForge;

    public function setIdeaForge(IdeaForge $ideaForge): static;

    public function setBriefBuilder(BriefBuilder $builder): static;

    public function getBriefBuilder(): BriefBuilder;

    public function setOutlineBuilder(OutlineBuilder $builder): static;

    public function getOutlineBuilder(): OutlineBuilder;

    public function setAuthor(Author $author): static;

    public function getAuthor(): Author;
}