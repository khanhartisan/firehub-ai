<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Contracts\CommonData\Verification verifyPoint(\App\Contracts\CommonData\Point $point, ?\App\Contracts\CommonData\SemanticContext $context = null)
 * @method static \App\Contracts\FactChecker\FactChecker driver(string|null $driver = null)
 *
 * @see \App\Services\FactChecker\FactCheckerManager
 */
class FactChecker extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'factchecker.manager';
    }
}
