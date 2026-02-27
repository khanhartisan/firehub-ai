<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Contracts\VerticalResolver\VerticalMatch[] resolve(string $content, \App\Contracts\VerticalResolver\Vertical[] $verticals)
 * @method static \App\Contracts\VerticalResolver\Vertical[] propose(string $content, \App\Contracts\VerticalResolver\Vertical[] $verticals)
 * @method static \App\Contracts\VerticalResolver\VerticalResolver driver(string|null $driver = null)
 *
 * @see \App\Services\VerticalResolver\VerticalResolverManager
 */
class VerticalResolver extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'vertical_resolver.manager';
    }
}
