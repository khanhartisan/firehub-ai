<?php

namespace App\Contracts\Model\Article;

use App\Concerns\Serializable;
use App\Contracts\Model\Article\StageData\IdeaStageData;
use App\Contracts\Synthesizer\Author\Draft;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;

final class StageData implements \App\Contracts\Serializable
{
    use Serializable;

    protected ?IdeaStageData $idea = null;
    protected ?Brief $brief = null;
    protected ?Outline $outline = null;
    protected ?Draft $draft = null;

    /**
     * @param array<string, mixed> $data
     * @throws \Exception
     */
    public function __construct(array $data = [])
    {
        if ($data) {
            $this->hydrateFromArray($data);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'idea' => $this->idea?->toArray(),
            'brief' => $this->brief?->toArray(),
            'outline' => $this->outline?->toArray(),
            'draft' => $this->draft?->toArray(),
        ], static fn ($v): bool => $v !== null);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static
    {
        return new static($data);
    }

    public function getIdeaStageData(): IdeaStageData
    {
        return $this->idea ??= new IdeaStageData;
    }

    public function setIdeaStageData(IdeaStageData $idea): static
    {
        $this->idea = $idea;

        return $this;
    }

    public function getBrief(): ?Brief
    {
        return $this->brief;
    }

    public function setBrief(Brief $brief): static
    {
        $this->brief = $brief;

        return $this;
    }

    public function getOutline(): ?Outline
    {
        return $this->outline;
    }

    public function setOutline(Outline $outline): static
    {
        $this->outline = $outline;

        return $this;
    }

    public function getDraft(): ?Draft
    {
        return $this->draft;
    }

    public function setDraft(Draft $draft): static
    {
        $this->draft = $draft;

        return $this;
    }

    public function getPickedIdea(): ?\App\Contracts\Synthesizer\IdeaForge\Idea
    {
        return $this->getIdeaStageData()->getPickedIdea();
    }

    /**
     * @throws \Exception
     */
    protected function hydrateFromArray(array $data): void
    {
        if (isset($data['idea']) && is_array($data['idea'])) {
            $this->setIdeaStageData(IdeaStageData::fromArray($data['idea']));
        }

        if (isset($data['brief']) && is_array($data['brief'])) {
            $this->setBrief(Brief::fromArray($data['brief']));
        }

        if (isset($data['outline']) && is_array($data['outline'])) {
            $this->setOutline(Outline::fromArray($data['outline']));
        }

        if (isset($data['draft']) && is_array($data['draft'])) {
            $this->setDraft(Draft::fromArray($data['draft']));
        }
    }
}
