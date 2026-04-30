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
    public function setRole(string $role): static
    {
        return $this->set(
            'role',
            'Narrative role of this character in the scene (e.g., hero, guide, observer).',
            $role
        );
    }

    public function setIdentity(string $identity): static
    {
        return $this->set(
            'identity',
            'Who this character is, including important archetype or background cues.',
            $identity
        );
    }

    public function setAppearance(string $appearance): static
    {
        return $this->set(
            'appearance',
            'Visible physical traits and overall look of this character.',
            $appearance
        );
    }

    public function setWardrobe(string $wardrobe): static
    {
        return $this->set(
            'wardrobe',
            'Clothing and accessories that define this character design.',
            $wardrobe
        );
    }

    public function setPose(string $pose): static
    {
        return $this->set(
            'pose',
            'Body posture and stance of this character.',
            $pose
        );
    }

    public function setPosition(string $position): static
    {
        return $this->set(
            'position',
            'Spatial placement of this character in the frame or scene.',
            $position
        );
    }

    public function setExpression(string $expression): static
    {
        return $this->set(
            'expression',
            'Facial expression and emotional tone of this character.',
            $expression
        );
    }

    public function setAction(string $action): static
    {
        return $this->set(
            'action',
            'What this character is doing in the scene.',
            $action
        );
    }

    public function setProps(array $props): static
    {
        return $this->set(
            'props',
            'Objects directly associated with this character.',
            array_values(array_filter($props, fn (mixed $prop): bool => is_string($prop) && $prop !== ''))
        );
    }

    public function setConstraints(array $constraints): static
    {
        return $this->set(
            'constraints',
            'Character-specific hard requirements or exclusions.',
            array_values(array_filter($constraints, fn (mixed $constraint): bool => is_string($constraint) && $constraint !== ''))
        );
    }
}