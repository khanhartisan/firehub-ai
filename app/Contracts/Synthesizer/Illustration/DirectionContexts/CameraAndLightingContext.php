<?php

namespace App\Contracts\Synthesizer\Illustration\DirectionContexts;

use App\Contracts\CommonData\SemanticContext;

/**
 * @method null|array getShotSize()
 * @method null|string getShotSizeValue()
 * @method null|string getShotSizeDescription()
 * @method null|array getCameraAngle()
 * @method null|string getCameraAngleValue()
 * @method null|string getCameraAngleDescription()
 * @method null|array getLenses()
 * @method null|array getLensesValue()
 * @method null|string getLensesDescription()
 * @method null|array getLighting()
 * @method null|string getLightingValue()
 * @method null|string getLightingDescription()
 * @method null|array getFilter()
 * @method null|string getFilterValue()
 * @method null|string getFilterDescription()
 * @method null|array getOptical()
 * @method null|string getOpticalValue()
 * @method null|string getOpticalDescription()
 * @method null|array getColorPalette()
 * @method null|string getColorPaletteValue()
 * @method null|string getColorPaletteDescription()
 * @method null|array getCompositionalRules()
 * @method null|array getCompositionalRulesValue()
 * @method null|string getCompositionalRulesDescription()
 * @method null|array getDepthPlan()
 * @method null|string getDepthPlanValue()
 * @method null|string getDepthPlanDescription()
 * @method null|array getNegativeConstraints()
 * @method null|array getNegativeConstraintsValue()
 * @method null|string getNegativeConstraintsDescription()
 */
class CameraAndLightingContext extends SemanticContext
{
    public function setShotSize(string $shotSize, ?float $weight = null): static
    {
        return $this->set(
            'shot_size',
            'Framing size of the subject (e.g., close-up, medium shot, wide shot).',
            $shotSize,
            $weight
        );
    }

    public function setCameraAngle(string $cameraAngle, ?float $weight = null): static
    {
        return $this->set(
            'camera_angle',
            'Camera viewpoint angle (e.g., eye-level, low-angle, top-down).',
            $cameraAngle,
            $weight
        );
    }

    public function setLenses(array $lenses, ?float $weight = null): static
    {
        return $this->set(
            'lenses',
            'Lens references and focal tendencies (e.g., 24mm wide, 85mm portrait).',
            array_values(array_filter($lenses, fn (mixed $lens): bool => is_string($lens) && $lens !== '')),
            $weight
        );
    }

    public function setLighting(string $lighting, ?float $weight = null): static
    {
        return $this->set(
            'lighting',
            'Primary lighting setup and quality (soft, hard, directional, rim-lit).',
            $lighting,
            $weight
        );
    }

    public function setFilter(string $filter, ?float $weight = null): static
    {
        return $this->set(
            'filter',
            'Creative filter or grade direction to apply to the scene.',
            $filter,
            $weight
        );
    }

    public function setOptical(string $optical, ?float $weight = null): static
    {
        return $this->set(
            'optical',
            'Optical behavior cues such as depth of field, bokeh, bloom, or distortion.',
            $optical,
            $weight
        );
    }

    public function setColorPalette(string $colorPalette, ?float $weight = null): static
    {
        return $this->set(
            'color_palette',
            'Color and grading direction for camera and lighting treatment.',
            $colorPalette,
            $weight
        );
    }

    public function setCompositionalRules(array $compositionalRules, ?float $weight = null): static
    {
        return $this->set(
            'compositional_rules',
            'Composition directives (e.g., rule of thirds, symmetry, leading lines).',
            array_values(array_filter(
                $compositionalRules,
                fn (mixed $rule): bool => is_string($rule) && $rule !== ''
            )),
            $weight
        );
    }

    public function setDepthPlan(string $depthPlan, ?float $weight = null): static
    {
        return $this->set(
            'depth_plan',
            'Foreground, midground, and background separation strategy.',
            $depthPlan,
            $weight
        );
    }

    public function setNegativeConstraints(array $negativeConstraints, ?float $weight = null): static
    {
        return $this->set(
            'negative_constraints',
            'Camera and lighting behaviors that should be explicitly avoided.',
            array_values(array_filter(
                $negativeConstraints,
                fn (mixed $constraint): bool => is_string($constraint) && $constraint !== ''
            )),
            $weight
        );
    }
}