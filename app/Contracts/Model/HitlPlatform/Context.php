<?php

namespace App\Contracts\Model\HitlPlatform;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\CommonData\SemanticContextConcerns\HasMeta;

/**
 * Platform-level context injected into HITL task agents as `platform_context`.
 *
 * @method null|string getNameValue()
 * @method null|string getDescriptionValue()
 * @method null|array getGuidelinesValue()
 * @method null|array getMetaValue()
 */
class Context extends SemanticContext
{
    use HasMeta;

    public function setName(?string $name, ?float $weight = null): static
    {
        return $this->set(
            'name',
            'Display name or label for this HITL platform setup.',
            $name,
            $weight
        );
    }

    public function setDescription(?string $description, ?float $weight = null): static
    {
        return $this->set(
            'description',
            'High-level description of how this HITL platform is used.',
            $description,
            $weight
        );
    }

    public function setGuidelines(array $guidelines, ?float $weight = null): static
    {
        return $this->set(
            'guidelines',
            'Standing instructions for humans and agents working through this platform.',
            array_values(array_filter($guidelines, fn ($guideline) => is_string($guideline))),
            $weight
        );
    }
}
