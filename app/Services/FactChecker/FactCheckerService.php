<?php

namespace App\Services\FactChecker;

use App\Contracts\CommonData\Point;
use App\Contracts\CommonData\SemanticContext;
use App\Contracts\CommonData\Verification;
use App\Contracts\FactChecker\FactChecker as FactCheckerContract;

abstract class FactCheckerService implements FactCheckerContract
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    abstract public function verifyPoint(Point $point, ?SemanticContext $context = null): Verification;
}
