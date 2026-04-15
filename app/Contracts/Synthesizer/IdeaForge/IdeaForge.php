<?php

namespace App\Contracts\Synthesizer\IdeaForge;

interface IdeaForge
{
    /**
     * @return IdeaAdvisor[]
     */
    public function getIdeaAdvisors(): array;

    /**
     * @param IdeaAdvisor[] $ideaAdvisors
     * @return static
     */
    public function setIdeaAdvisors(array $ideaAdvisors): static;

    public function addIdeaAdvisor(IdeaAdvisor $ideaAdvisor): static;

    public function getIdeaAuditor(): IdeaAuditor;

    public function setIdeaAuditor(IdeaAuditor $auditor): static;

    public function getIdeaPicker(): IdeaPicker;

    public function setIdeaPicker(IdeaPicker $picker): static;
}