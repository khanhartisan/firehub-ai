<?php

namespace App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptContexts;

use App\Contracts\CommonData\SemanticContext;

/**
 * @method null|array getTheme()
 * @method null|string getThemeValue()
 * @method null|string getThemeDescription()
 * @method null|array getMetaphor()
 * @method null|string getMetaphorValue()
 * @method null|string getMetaphorDescription()
 * @method null|array getNarrativeArc()
 * @method null|string getNarrativeArcValue()
 * @method null|string getNarrativeArcDescription()
 * @method null|array getDominantShapes()
 * @method null|array getDominantShapesValue()
 * @method null|string getDominantShapesDescription()
 * @method null|array getSymbolicElements()
 * @method null|array getSymbolicElementsValue()
 * @method null|string getSymbolicElementsDescription()
 * @method null|array getMood()
 * @method null|string getMoodValue()
 * @method null|string getMoodDescription()
 * @method null|array getVisualTension()
 * @method null|string getVisualTensionValue()
 * @method null|string getVisualTensionDescription()
 * @method null|array getConstraints()
 * @method null|array getConstraintsValue()
 * @method null|string getConstraintsDescription()
 */
class AbstractionContext extends SemanticContext
{
    public function setTheme(string $theme): static
    {
        return $this->set(
            'theme',
            'Core abstract theme this visual should communicate.',
            $theme
        );
    }

    public function setMetaphor(string $metaphor): static
    {
        return $this->set(
            'metaphor',
            'Primary metaphor or symbolic mapping used in the concept.',
            $metaphor
        );
    }

    public function setNarrativeArc(string $narrativeArc): static
    {
        return $this->set(
            'narrative_arc',
            'Abstract story progression represented by the composition.',
            $narrativeArc
        );
    }

    public function setDominantShapes(array $dominantShapes): static
    {
        return $this->set(
            'dominant_shapes',
            'Primary geometric or organic shapes that drive the abstract language.',
            array_values(array_filter($dominantShapes, fn (mixed $shape): bool => is_string($shape) && $shape !== ''))
        );
    }

    public function setSymbolicElements(array $symbolicElements): static
    {
        return $this->set(
            'symbolic_elements',
            'Symbolic elements that must appear to preserve conceptual meaning.',
            array_values(array_filter($symbolicElements, fn (mixed $element): bool => is_string($element) && $element !== ''))
        );
    }

    public function setMood(string $mood): static
    {
        return $this->set(
            'mood',
            'Emotional atmosphere the abstract concept should evoke.',
            $mood
        );
    }

    public function setVisualTension(string $visualTension): static
    {
        return $this->set(
            'visual_tension',
            'How contrast, balance, and conflict should be expressed visually.',
            $visualTension
        );
    }

    public function setConstraints(array $constraints): static
    {
        return $this->set(
            'constraints',
            'Hard constraints for the abstract concept rendering.',
            array_values(array_filter($constraints, fn (mixed $constraint): bool => is_string($constraint) && $constraint !== ''))
        );
    }
}