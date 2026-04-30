<?php

namespace App\Services\Synthesizer\Illustration\Director\Drivers;

use App\Contracts\Synthesizer\Illustration\DirectionContexts\ArtStyleContext;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\CameraAndLightingContext;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptContext;
use App\Contracts\Synthesizer\Illustration\Director;
use App\Contracts\Synthesizer\Illustration\IllustrationContext;
use App\Contracts\Synthesizer\Illustration\IllustrationDirection;
use App\Contracts\Synthesizer\Illustration\Illustratable;
use App\Contracts\Synthesizer\Illustration\Illustrator;
use App\Services\Synthesizer\Illustration\Director\DirectorService;
use App\Services\Synthesizer\Illustration\Illustrator\Drivers\BasicIllustratorDriver;

class BasicDirectorDriver extends DirectorService implements Director
{
    public function __construct(
        protected ?Illustrator $fallbackIllustrator = null,
    ) {
        $this->fallbackIllustrator ??= new BasicIllustratorDriver();
    }

    public function resolveIllustrationContexts(
        Illustratable $illustratable,
        ?int $minContexts = null,
        ?int $maxContexts = null,
    ): array {
        $content = trim($illustratable->getIllustrationContent());
        if ($content === '') {
            return [];
        }

        $min = max(1, (int) ($minContexts ?? 1));
        $max = max($min, (int) ($maxContexts ?? $min));
        $max = min($max, 5);

        $segments = preg_split('/(?<=[.!?])\s+/', $content) ?: [];
        $segments = array_values(array_filter(array_map(
            static fn (string $segment): string => trim($segment),
            $segments
        )));

        if ($segments === []) {
            $segments = [$content];
        }

        $contexts = [];
        foreach ($segments as $segment) {
            if (count($contexts) >= $max) {
                break;
            }

            $contexts[] = (new IllustrationContext())
                ->setSubject($segment)
                ->setGoal('Visually support the associated article segment.');
        }

        while (count($contexts) < $min) {
            $contexts[] = (new IllustrationContext())
                ->setSubject($content)
                ->setGoal('Visually support the associated article segment.');
        }

        return $contexts;
    }

    public function direct(IllustrationContext $context): IllustrationDirection
    {
        $conceptContext = new ConceptContext();
        $artStyleContext = new ArtStyleContext();
        $cameraAndLightingContext = new CameraAndLightingContext();

        $subject = $context->getSubjectValue();
        if (is_string($subject) && trim($subject) !== '') {
            $conceptContext->setPrimarySubject($subject);
        }

        $goal = $context->getGoalValue();
        if (is_string($goal) && trim($goal) !== '') {
            $conceptContext->setNarrativeIntent($goal);
        }

        $macro = $context->getMacroContextValue();
        if (is_string($macro) && trim($macro) !== '') {
            $conceptContext->setLogline($macro);
            $conceptContext->setSceneContext($macro);
        }

        $style = $context->getStyleValue();
        if (is_string($style) && trim($style) !== '') {
            $artStyleContext->setStyle($style);
        }

        $micro = $context->getMicroContextValue();
        if (is_string($micro) && trim($micro) !== '') {
            $cameraAndLightingContext->setCompositionalRules([$micro]);
        }

        return (new IllustrationDirection())
            ->setConceptContext($conceptContext)
            ->setArtStyleContext($artStyleContext)
            ->setCameraAndLightingContext($cameraAndLightingContext);
    }

    public function determineIllustrator(
        IllustrationContext $context,
        IllustrationDirection $direction,
        array $illustrators
    ): ?Illustrator {
        foreach ($illustrators as $illustrator) {
            if ($illustrator instanceof Illustrator) {
                return $illustrator;
            }
        }

        return $this->fallbackIllustrator;
    }
}

