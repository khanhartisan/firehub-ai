<?php

namespace App\Contracts\FactChecker;

use App\Contracts\CommonData\Point;
use App\Contracts\CommonData\SemanticContext;
use App\Contracts\CommonData\Verification;

interface FactChecker
{
    public function verifyPoint(Point $point, ?SemanticContext $context = null): Verification;
}