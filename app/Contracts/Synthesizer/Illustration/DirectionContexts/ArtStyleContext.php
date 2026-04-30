<?php

namespace App\Contracts\Synthesizer\Illustration\DirectionContexts;

use App\Contracts\CommonData\SemanticContext;
use App\Enums\ArtMedium;

/**
 * @method null|array getArtMedium()
 * @method null|string getArtMediumValue()
 * @method null|string getArtMediumDescription()
 * @method null|array getStyle()
 * @method null|string getStyleValue()
 * @method null|string getStyleDescription()
 * @method null|array getCreatorReferences()
 * @method null|array getCreatorReferencesValue()
 * @method null|string getCreatorReferencesDescription()
 * @method null|array getColorPalette()
 * @method null|string getColorPaletteValue()
 * @method null|string getColorPaletteDescription()
 * @method null|array getOverallVibe()
 * @method null|string getOverallVibeValue()
 * @method null|string getOverallVibeDescription()
 * @method null|array getRenderingDetails()
 * @method null|string getRenderingDetailsValue()
 * @method null|string getRenderingDetailsDescription()
 * @method null|array getNegativeStyleConstraints()
 * @method null|array getNegativeStyleConstraintsValue()
 * @method null|string getNegativeStyleConstraintsDescription()
 */
class ArtStyleContext extends SemanticContext
{
    public function setArtMedium(?ArtMedium $artMedium): static
    {
        return $this->set(
            'art_medium',
            'Primary image medium choice (photography, 2D illustration, or 3D illustration).',
            $artMedium?->value
        );
    }

    public function setStyle(string $style): static
    {
        return $this->set(
            'style',
            'Named art style direction (e.g., minimal editorial vector, cinematic realism).',
            $style
        );
    }

    public function setCreatorReferences(array $references): static
    {
        return $this->set(
            'creator_references',
            'Creator, studio, or movement references to emulate stylistically.',
            array_values(array_filter($references, fn (mixed $reference): bool => is_string($reference) && $reference !== ''))
        );
    }

    public function setColorPalette(string $colorPalette): static
    {
        return $this->set(
            'color_palette',
            'Color direction for the final visual (tones, harmony, contrast).',
            $colorPalette
        );
    }

    public function setOverallVibe(string $overallVibe): static
    {
        return $this->set(
            'overall_vibe',
            'Overall emotional and aesthetic vibe the image should convey.',
            $overallVibe
        );
    }

    public function setRenderingDetails(string $renderingDetails): static
    {
        return $this->set(
            'rendering_details',
            'Detail-level and finish instructions (clean lines, painterly brushwork, grain, etc.).',
            $renderingDetails
        );
    }

    public function setNegativeStyleConstraints(array $negativeStyleConstraints): static
    {
        return $this->set(
            'negative_style_constraints',
            'Stylistic elements to avoid in the final output.',
            array_values(array_filter(
                $negativeStyleConstraints,
                fn (mixed $constraint): bool => is_string($constraint) && $constraint !== ''
            ))
        );
    }
}