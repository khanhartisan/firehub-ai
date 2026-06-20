<?php

namespace App\Mcp\Support;

use App\Mcp\Tools\Tool;

final class McpToolName
{
    /** @var array<class-string<Tool>, string> */
    private static array $cache = [];

    /**
     * @param  class-string<Tool>  $toolClass
     */
    public static function resolve(string $toolClass): string
    {
        return self::$cache[$toolClass] ??= (new $toolClass)->name();
    }

    /**
     * @param  class-string<Tool>  $toolClass
     */
    public static function quoted(string $toolClass): string
    {
        return '`'.self::resolve($toolClass).'`';
    }

    /**
     * @param  list<class-string<Tool>>  $toolClasses
     */
    public static function quotedList(array $toolClasses, string $conjunction = 'or'): string
    {
        $quoted = array_map(self::quoted(...), $toolClasses);

        if ($quoted === []) {
            return '';
        }

        if (count($quoted) === 1) {
            return $quoted[0];
        }

        $last = array_pop($quoted);

        return implode(', ', $quoted).' '.$conjunction.' '.$last;
    }

    /**
     * @param  array<string, class-string<Tool>>  $tools
     */
    public static function quotedFromMap(array $tools, string ...$keys): string
    {
        $classes = [];

        foreach ($keys as $key) {
            if (! isset($tools[$key])) {
                throw new \InvalidArgumentException("Unknown tool key [{$key}].");
            }

            $classes[] = $tools[$key];
        }

        return self::quotedList($classes);
    }
}
