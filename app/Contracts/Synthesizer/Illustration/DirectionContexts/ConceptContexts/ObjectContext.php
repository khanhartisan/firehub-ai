<?php

namespace App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptContexts;

use App\Contracts\CommonData\SemanticContext;

/**
 * @method null|array getName()
 * @method null|string getNameValue()
 * @method null|string getNameDescription()
 * @method null|array getType()
 * @method null|string getTypeValue()
 * @method null|string getTypeDescription()
 * @method null|array getAppearance()
 * @method null|string getAppearanceValue()
 * @method null|string getAppearanceDescription()
 * @method null|array getMaterial()
 * @method null|string getMaterialValue()
 * @method null|string getMaterialDescription()
 * @method null|array getCondition()
 * @method null|string getConditionValue()
 * @method null|string getConditionDescription()
 * @method null|array getPosition()
 * @method null|string getPositionValue()
 * @method null|string getPositionDescription()
 * @method null|array getScale()
 * @method null|string getScaleValue()
 * @method null|string getScaleDescription()
 * @method null|array getInteraction()
 * @method null|string getInteractionValue()
 * @method null|string getInteractionDescription()
 * @method null|array getConstraints()
 * @method null|array getConstraintsValue()
 * @method null|string getConstraintsDescription()
 */
class ObjectContext extends SemanticContext
{
    public function setName(string $name): static
    {
        return $this->set(
            'name',
            'Primary object name or label.',
            $name
        );
    }

    public function setType(string $type): static
    {
        return $this->set(
            'type',
            'Object category or class (e.g., tool, vehicle, furniture).',
            $type
        );
    }

    public function setAppearance(string $appearance): static
    {
        return $this->set(
            'appearance',
            'Visual appearance details including form and surface cues.',
            $appearance
        );
    }

    public function setMaterial(string $material): static
    {
        return $this->set(
            'material',
            'Primary material or texture characteristics of this object.',
            $material
        );
    }

    public function setCondition(string $condition): static
    {
        return $this->set(
            'condition',
            'Wear state or condition of the object (new, worn, damaged, etc.).',
            $condition
        );
    }

    public function setPosition(string $position): static
    {
        return $this->set(
            'position',
            'Spatial position of the object within the scene or frame.',
            $position
        );
    }

    public function setScale(string $scale): static
    {
        return $this->set(
            'scale',
            'Relative size of this object compared to nearby elements.',
            $scale
        );
    }

    public function setInteraction(string $interaction): static
    {
        return $this->set(
            'interaction',
            'How this object interacts with characters or other objects.',
            $interaction
        );
    }

    public function setConstraints(array $constraints): static
    {
        return $this->set(
            'constraints',
            'Object-specific hard requirements or exclusions.',
            array_values(array_filter($constraints, fn (mixed $constraint): bool => is_string($constraint) && $constraint !== ''))
        );
    }
}