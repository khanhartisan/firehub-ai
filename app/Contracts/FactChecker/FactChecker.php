<?php

namespace App\Contracts\FactChecker;

use App\Contracts\CommonData\Conflict;
use App\Contracts\CommonData\Fact;
use App\Contracts\CommonData\SemanticContext;
use App\Contracts\CommonData\Verification;

interface FactChecker
{
    public function verify(FactCheckable $factCheckable, ?SemanticContext $context = null): Verification;

    /**
     * Resolve a conflict and return an array of facts
     *
     * @param Conflict $conflict
     * @param SemanticContext|null $context
     * @return Fact[]
     */
    public function resolveConflict(Conflict $conflict, ?SemanticContext $context = null): array;
}