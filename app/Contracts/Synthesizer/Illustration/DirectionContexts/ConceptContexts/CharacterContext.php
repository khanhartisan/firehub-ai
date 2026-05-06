<?php

namespace App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptContexts;

use App\Contracts\CommonData\SemanticContext;

/**
 * @method null|array getRole()
 * @method null|string getRoleValue()
 * @method null|string getRoleDescription()
 * @method null|array getIdentity()
 * @method null|string getIdentityValue()
 * @method null|string getIdentityDescription()
 * @method null|array getAppearance()
 * @method null|string getAppearanceValue()
 * @method null|string getAppearanceDescription()
 * @method null|array getWardrobe()
 * @method null|string getWardrobeValue()
 * @method null|string getWardrobeDescription()
 * @method null|array getPose()
 * @method null|string getPoseValue()
 * @method null|string getPoseDescription()
 * @method null|array getPosition()
 * @method null|string getPositionValue()
 * @method null|string getPositionDescription()
 * @method null|array getExpression()
 * @method null|string getExpressionValue()
 * @method null|string getExpressionDescription()
 * @method null|array getAction()
 * @method null|string getActionValue()
 * @method null|string getActionDescription()
 * @method null|array getProps()
 * @method null|array getPropsValue()
 * @method null|string getPropsDescription()
 * @method null|array getConstraints()
 * @method null|array getConstraintsValue()
 * @method null|string getConstraintsDescription()
 */
class CharacterContext extends SemanticContext
{
    public function setRole(string $role, ?float $weight = null): static
    {
        return $this->set(
            'role',
            'Narrative role of this character in the scene (e.g., hero, guide, observer).',
            $role,
            $weight
        );
    }

    public function setIdentity(string $identity, ?float $weight = null): static
    {
        return $this->set(
            'identity',
            'Who this character is, including important archetype or background cues.',
            $identity,
            $weight
        );
    }

    public function setAppearance(string $appearance, ?float $weight = null): static
    {
        return $this->set(
            'appearance',
            'Visible physical traits and overall look of this character.',
            $appearance,
            $weight
        );
    }

    public function setWardrobe(string $wardrobe, ?float $weight = null): static
    {
        return $this->set(
            'wardrobe',
            'Clothing and accessories that define this character design.',
            $wardrobe,
            $weight
        );
    }

    public function setPose(string $pose, ?float $weight = null): static
    {
        return $this->set(
            'pose',
            'Body posture and stance of this character.',
            $pose,
            $weight
        );
    }

    public function setPosition(string $position, ?float $weight = null): static
    {
        return $this->set(
            'position',
            'Spatial placement of this character in the frame or scene.',
            $position,
            $weight
        );
    }

    public function setExpression(string $expression, ?float $weight = null): static
    {
        return $this->set(
            'expression',
            'Facial expression and emotional tone of this character.',
            $expression,
            $weight
        );
    }

    public function setAction(string $action, ?float $weight = null): static
    {
        return $this->set(
            'action',
            'What this character is doing in the scene.',
            $action,
            $weight
        );
    }

    public function setProps(array $props, ?float $weight = null): static
    {
        return $this->set(
            'props',
            'Objects directly associated with this character.',
            array_values(array_filter($props, fn (mixed $prop): bool => is_string($prop) && $prop !== '')),
            $weight
        );
    }

    public function setConstraints(array $constraints, ?float $weight = null): static
    {
        return $this->set(
            'constraints',
            'Character-specific hard requirements or exclusions.',
            array_values(array_filter($constraints, fn (mixed $constraint): bool => is_string($constraint) && $constraint !== '')),
            $weight
        );
    }
}