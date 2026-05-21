<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns;

use App\Contracts\CommonData\Fact;
use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Facades\FactChecker;

trait HandleResearchStageConflictResolution
{
    protected function resolveOneConflict(Idea $pickedIdea): bool
    {
        $researchData = $this->getStageData()->getResearchStageData();
        $conflict = $researchData->shiftConflict();
        if ($conflict === null) {
            return false;
        }

        $facts = FactChecker::driver()->resolveConflict($conflict, $this->buildFactResolutionContext($pickedIdea));
        $highConfidenceFacts = array_values(array_filter(
            $facts,
            static function (Fact $fact): bool {
                $confidence = $fact->getVerification()?->getConfidence();

                return $confidence !== null && $confidence >= 0.8;
            }
        ));

        $resolvedPoint = $this->synthesizer()
            ->getResearcher()
            ->resolveIdeaConflictedPointsByFacts($pickedIdea, $conflict, $highConfidenceFacts);

        if ($resolvedPoint === null) {
            $researchData->addUnresolvableConflict($conflict);
        } else {
            $researchData->addResolvedConflictedPoint($resolvedPoint);
        }

        $this->touchArticleQuietly();

        return true;
    }

    protected function consolidateResolvedConflictPoints(Idea $pickedIdea): bool
    {
        $researchData = $this->getStageData()->getResearchStageData();
        $resolvedPoints = $researchData->getResolvedConflictedPoints();
        if ($resolvedPoints === []) {
            return false;
        }

        $input = array_merge($researchData->getPoints(), $resolvedPoints);
        $result = $this->synthesizer()
            ->getResearcher()
            ->consolidateIdeaPoints($pickedIdea, $input);

        $researchData->setPoints($result->getPoints());
        $researchData->setConflicts(array_merge($researchData->getConflicts(), $result->getConflicts()));
        $researchData->setResolvedConflictedPoints([]);

        $this->touchArticleQuietly();

        return true;
    }

    protected function buildFactResolutionContext(Idea $idea): SemanticContext
    {
        $context = new SemanticContext;
        $context->set(
            'task',
            'Instruction for fact conflict resolution.',
            'Resolve conflicting claims into a defensible set of verified facts. Focus only on factual evidence.'
        );
        $context->set(
            'idea',
            'The editorial idea that resolved facts should stay aligned with.',
            $idea
        );

        return $context;
    }
}
