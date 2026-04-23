<?php

namespace App\Services\FactChecker\Drivers;

use App\Contracts\CommonData\Conflict;
use App\Contracts\CommonData\Fact;
use App\Contracts\CommonData\Point;
use App\Contracts\CommonData\SemanticContext;
use App\Contracts\CommonData\Verification;
use App\Contracts\FactChecker\FactCheckable;
use App\Services\FactChecker\FactCheckerService;

class BasicFactCheckerDriver extends FactCheckerService
{
    public function verify(FactCheckable $factCheckable, ?SemanticContext $context = null): Verification
    {
        $factClaim = trim($factCheckable->getFactClaim());
        $hasClaim = $factClaim !== '';
        $evidenceCount = 0;
        $headline = '';
        $description = '';
        if ($factCheckable instanceof Point) {
            $headline = trim((string) $factCheckable->getHeadline());
            $description = trim((string) $factCheckable->getDescription());
            $evidenceCount = count(array_values(array_filter(
                $factCheckable->getEvidences(),
                static fn (string $evidence): bool => trim($evidence) !== ''
            )));
            $hasClaim = $hasClaim || $headline !== '' || $description !== '';
        }

        $score = 0.2;
        if ($headline !== '') {
            $score += 0.2;
        }
        if ($description !== '') {
            $score += 0.2;
        }
        if ($evidenceCount > 0) {
            $score += min(0.4, 0.15 + ($evidenceCount * 0.1));
        }
        if ($context && count($context->toArray()) > 0) {
            $score += 0.05;
        }

        $confidence = max(0.0, min(1.0, round($score, 2)));
        $minConfidence = (float) ($this->config['min_confidence'] ?? 0.6);
        $isValid = $hasClaim && $evidenceCount > 0 && $confidence >= $minConfidence;

        $reasoning = sprintf(
            'Basic verification completed with %d evidence item(s); confidence %.2f (threshold %.2f).',
            $evidenceCount,
            $confidence,
            $minConfidence
        );

        return (new Verification)
            ->setIsValid($isValid)
            ->setConfidence($confidence)
            ->setReasoning($reasoning);
    }

    /**
     * @return Fact[]
     */
    public function resolveConflict(Conflict $conflict, ?SemanticContext $context = null): array
    {
        return $conflict->getFacts();
    }
}
