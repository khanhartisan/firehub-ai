<?php

namespace App\Mcp\Support\Guidelines;

use Laravel\Mcp\Server\Resource;

final class GuidelinesBreadcrumb
{
    /**
     * @param  list<class-string<Resource>>  $parentResourceClasses
     * @param  class-string<Resource>  $currentResourceClass
     */
    public static function render(array $parentResourceClasses, string $currentResourceClass): string
    {
        $crumbs = [];

        foreach ($parentResourceClasses as $resourceClass) {
            $crumbs[] = self::linkedCrumb($resourceClass);
        }

        $current = McpResourceReference::fromResourceClass($currentResourceClass);
        $crumbs[] = '**'.$current['title'].'** (`'.$current['uri'].'`)';

        return '**You are here:** '.implode(' → ', $crumbs);
    }

    /**
     * @param  class-string<Resource>  $resourceClass
     */
    private static function linkedCrumb(string $resourceClass): string
    {
        $reference = McpResourceReference::fromResourceClass($resourceClass);

        return '['.$reference['title'].']('.$reference['uri'].')';
    }
}
