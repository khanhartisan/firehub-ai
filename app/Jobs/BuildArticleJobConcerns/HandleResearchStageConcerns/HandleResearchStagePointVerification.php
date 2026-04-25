<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Facades\FactChecker;

trait HandleResearchStagePointVerification
{
    protected function verifyOnePendingPoint(Idea $pickedIdea): bool
    {
        $researchData = $this->getStageData()->getResearchStageData();
        $points = $researchData->getPoints();

        foreach ($points as $index => $point) {
            if (! $point instanceof RelevantPoint) {
                continue;
            }

            if ($point->getVerification() !== null) {
                continue;
            }

            $verification = FactChecker::driver()->verify(
                $point,
                $this->buildPointVerificationContext($pickedIdea)
            );
            $point->setVerification($verification);
            $points[$index] = $point;

            $researchData->setPoints($points);
            $this->touchArticleQuietly();

            return true;
        }

        return false;
    }

    protected function removeLowConfidencePoints(): bool
    {
        $researchData = $this->getStageData()->getResearchStageData();
        $points = $researchData->getPoints();
        if ($points === []) {
            return false;
        }

        $filtered = array_values(array_filter(
            $points,
            static function (RelevantPoint $point): bool {
                $confidence = $point->getVerification()?->getConfidence() ?? 0;

                return $confidence > 0.8;
            }
        ));

        if (count($filtered) === count($points)) {
            return false;
        }

        $researchData->setPoints($filtered);
        $this->touchArticleQuietly();

        return true;
    }

    protected function buildPointVerificationContext(Idea $idea): SemanticContext
    {
        $context = new SemanticContext;
        $context->set(
            'task',
            'Instruction for point verification.',
            'Verify factual accuracy and evidence support for this point.'
        );
        $context->set(
            'idea',
            'The editorial idea the verified point should align with.',
            $idea
        );

        return $context;
    }
}
