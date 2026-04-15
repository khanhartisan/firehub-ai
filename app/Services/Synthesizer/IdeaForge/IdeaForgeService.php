<?php

namespace App\Services\Synthesizer\IdeaForge;

use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditor;
use App\Contracts\Synthesizer\IdeaForge\IdeaForge;
use App\Contracts\Synthesizer\IdeaForge\IdeaPicker;

abstract class IdeaForgeService implements IdeaForge
{
    /** @var IdeaAdvisor[] */
    protected array $ideaAdvisors = [];

    public function __construct(
        array $ideaAdvisors = [],
        protected ?IdeaAuditor $ideaAuditor = null,
        protected ?IdeaPicker $ideaPicker = null,
    ) {
        $this->setIdeaAdvisors($ideaAdvisors);
    }

    public function getIdeaAdvisors(): array
    {
        return $this->ideaAdvisors;
    }

    public function setIdeaAdvisors(array $ideaAdvisors): static
    {
        $this->ideaAdvisors = [];

        foreach ($ideaAdvisors as $ideaAdvisor) {
            if ($ideaAdvisor instanceof IdeaAdvisor) {
                $this->addIdeaAdvisor($ideaAdvisor);
            }
        }

        return $this;
    }

    public function addIdeaAdvisor(IdeaAdvisor $ideaAdvisor): static
    {
        $this->ideaAdvisors[] = $ideaAdvisor;

        return $this;
    }

    public function getIdeaAuditor(): IdeaAuditor
    {
        if (! $this->ideaAuditor instanceof IdeaAuditor) {
            throw new \RuntimeException('Idea auditor has not been configured.');
        }

        return $this->ideaAuditor;
    }

    public function setIdeaAuditor(IdeaAuditor $auditor): static
    {
        $this->ideaAuditor = $auditor;

        return $this;
    }

    public function getIdeaPicker(): IdeaPicker
    {
        if (! $this->ideaPicker instanceof IdeaPicker) {
            throw new \RuntimeException('Idea picker has not been configured.');
        }

        return $this->ideaPicker;
    }

    public function setIdeaPicker(IdeaPicker $picker): static
    {
        $this->ideaPicker = $picker;

        return $this;
    }
}
