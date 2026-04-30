<?php

namespace App\Contracts\Synthesizer;

use App\Contracts\Synthesizer\Author\Author;
use App\Contracts\Synthesizer\BriefBuilder\BriefBuilder;
use App\Contracts\Synthesizer\IdeaForge\IdeaForge;
use App\Contracts\Synthesizer\Illustration\Director;
use App\Contracts\Synthesizer\Illustration\Illustrator;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineBuilder;
use App\Contracts\Synthesizer\Researcher\Researcher;

interface Synthesizer
{
    public function getIdeaForge(): IdeaForge;

    public function setIdeaForge(IdeaForge $ideaForge): static;

    public function getResearcher(): Researcher;

    public function setResearcher(Researcher $researcher): static;

    public function setBriefBuilder(BriefBuilder $builder): static;

    public function getBriefBuilder(): BriefBuilder;

    public function setOutlineBuilder(OutlineBuilder $builder): static;

    public function getOutlineBuilder(): OutlineBuilder;

    public function setAuthor(Author $author): static;

    public function getAuthor(): Author;

    public function setIllustrationDirector(Director $director): static;

    public function getIllustrationDirector(): Director;

    /**
     * @param Illustrator[] $illustrators
     * @return static
     */
    public function setIllustrators(array $illustrators): static;

    /**
     * @return Illustrator[]
     */
    public function getIllustrators(): array;
}