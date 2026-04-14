<?php

namespace App\Contracts\Synthesizer\IdeaForge;

interface IdeaForge
{
    public function isIdeaUnique(string $clientId, string $ideaSummary): IdeaUniquenessReport;

    public function getAudienceIdeaAdvisor(): IdeaAdvisor;

    public function setAudienceIdeaAdvisor(IdeaAdvisor $advisor): static;

    public function getOwnerIdeaAdvisor(): IdeaAdvisor;

    public function setOwnerIdeaAdvisor(IdeaAdvisor $advisor): static;
}