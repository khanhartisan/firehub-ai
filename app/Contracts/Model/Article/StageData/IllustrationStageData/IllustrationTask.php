<?php

namespace App\Contracts\Model\Article\StageData\IllustrationStageData;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;
use App\Contracts\Synthesizer\Illustration\IllustrationContext;
use App\Contracts\Synthesizer\Illustration\IllustrationDirection;

final class IllustrationTask implements Serializable
{
    use SerializableTrait;

    protected ?IllustrationContext $illustrationContext = null;

    protected ?IllustrationDirection $illustrationDirection = null;

    public function getIllustrationContext(): ?IllustrationContext
    {
        return $this->illustrationContext;
    }

    public function setIllustrationContext(IllustrationContext $illustrationContext): static
    {
        $illustrationContext->getIdentifier();
        $this->illustrationContext = $illustrationContext;

        return $this;
    }

    public function getIllustrationDirection(): ?IllustrationDirection
    {
        return $this->illustrationDirection;
    }

    public function setIllustrationDirection(?IllustrationDirection $illustrationDirection): static
    {
        $this->illustrationDirection = $illustrationDirection;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'illustration_context' => $this->illustrationContext?->toArray(),
            'illustration_direction' => $this->illustrationDirection?->toArray(),
        ];
    }

    public static function fromArray(array $data): static
    {
        $obj = new static;

        if (isset($data['illustration_context']) && is_array($data['illustration_context'])) {
            $obj->setIllustrationContext(IllustrationContext::fromArray($data['illustration_context']));
        }

        if (isset($data['illustration_direction']) && is_array($data['illustration_direction'])) {
            $obj->setIllustrationDirection(IllustrationDirection::fromArray($data['illustration_direction']));
        }

        return $obj;
    }
}
