<?php

namespace App\Contracts\Synthesizer\IdeaForge;

interface IdeaForge
{
    public function getAudienceIdeaAdvisor(): IdeaAdvisor;

    public function setAudienceIdeaAdvisor(IdeaAdvisor $advisor): static;

    public function getOwnerIdeaAdvisor(): IdeaAdvisor;

    public function setOwnerIdeaAdvisor(IdeaAdvisor $advisor): static;

    public function getResearcherIdeaAdvisor(): IdeaAdvisor;

    public function setResearcherIdeaAdvisor(IdeaAdvisor $advisor): static;

    public function getIdeaAuditor(): IdeaAuditor;

    public function setIdeaAuditor(IdeaAuditor $auditor): static;

    public function getIdeaPicker(): IdeaPicker;

    public function setIdeaPicker(IdeaPicker $picker): static;
}