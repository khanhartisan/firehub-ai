<?php

namespace App\Contracts\Synthesizer\Illustration;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ArtStyleContext;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\CameraAndLightingContext;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptContext;

/**
 * @method null|array getConceptContext()
 * @method null|array getConceptContextValue()
 * @method null|string getConceptContextDescription()
 * @method null|array getArtStyleContext()
 * @method null|array getArtStyleContextValue()
 * @method null|string getArtStyleContextDescription()
 * @method null|array getCameraAndLightingContext()
 * @method null|array getCameraAndLightingContextValue()
 * @method null|string getCameraAndLightingContextDescription()
 */
class IllustrationDirection extends SemanticContext
{
    public function setConceptContext(?ConceptContext $conceptContext): static
    {
        return $this->set(
            'concept_context',
            'Top-level concept direction including narrative, subjects, and scene constraints.',
            $conceptContext
        );
    }

    public function setArtStyleContext(?ArtStyleContext $artStyleContext): static
    {
        return $this->set(
            'art_style_context',
            'Art style direction covering medium, stylistic references, and rendering preferences.',
            $artStyleContext
        );
    }

    public function setCameraAndLightingContext(?CameraAndLightingContext $cameraAndLightingContext): static
    {
        return $this->set(
            'camera_and_lighting_context',
            'Camera framing and lighting direction for composition and visual atmosphere.',
            $cameraAndLightingContext
        );
    }
}