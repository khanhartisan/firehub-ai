<?php

namespace App\Contracts\FactChecker;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\CommonData\Verification;

interface FactChecker
{
    public function verify(FactCheckable $factCheckable, ?SemanticContext $context = null): Verification;
}