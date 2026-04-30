<?php

namespace App\Services\Synthesizer;

use App\Contracts\Synthesizer\Author\Author;
use App\Contracts\Synthesizer\BriefBuilder\BriefBuilder;
use App\Contracts\Synthesizer\IdeaForge\IdeaForge;
use App\Contracts\Synthesizer\Illustration\Director;
use App\Contracts\Synthesizer\Illustration\Illustrator;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineBuilder;
use App\Contracts\Synthesizer\Researcher\Researcher;
use App\Contracts\Synthesizer\Synthesizer;

class SynthesizerService implements Synthesizer
{
    public function __construct(
        protected IdeaForge $ideaForge,
        protected Researcher $researcher,
        protected BriefBuilder $briefBuilder,
        protected OutlineBuilder $outlineBuilder,
        protected Author $author,
        protected Director $illustrationDirector,
        protected array $illustrators = [],
    ) {
    }

    public function getIdeaForge(): IdeaForge
    {
        return $this->ideaForge;
    }

    public function setIdeaForge(IdeaForge $ideaForge): static
    {
        $this->ideaForge = $ideaForge;

        return $this;
    }

    public function getResearcher(): Researcher
    {
        return $this->researcher;
    }

    public function setResearcher(Researcher $researcher): static
    {
        $this->researcher = $researcher;

        return $this;
    }

    public function setBriefBuilder(BriefBuilder $builder): static
    {
        $this->briefBuilder = $builder;

        return $this;
    }

    public function getBriefBuilder(): BriefBuilder
    {
        return $this->briefBuilder;
    }

    public function setOutlineBuilder(OutlineBuilder $builder): static
    {
        $this->outlineBuilder = $builder;

        return $this;
    }

    public function getOutlineBuilder(): OutlineBuilder
    {
        return $this->outlineBuilder;
    }

    public function setAuthor(Author $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getAuthor(): Author
    {
        return $this->author;
    }

    public function setIllustrationDirector(Director $director): static
    {
        $this->illustrationDirector = $director;

        return $this;
    }

    public function getIllustrationDirector(): Director
    {
        return $this->illustrationDirector;
    }

    /**
     * @param  Illustrator[]  $illustrators
     */
    public function setIllustrators(array $illustrators): static
    {
        $this->illustrators = array_values(array_filter(
            $illustrators,
            static fn (mixed $illustrator): bool => $illustrator instanceof Illustrator
        ));

        return $this;
    }

    /**
     * @return Illustrator[]
     */
    public function getIllustrators(): array
    {
        return $this->illustrators;
    }
}
