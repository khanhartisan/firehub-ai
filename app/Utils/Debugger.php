<?php

namespace App\Utils;

class Debugger
{
    public static function devConsoleDump(...$args): void
    {
        if (!env('APP_DEBUG')
            or !app()->runningInConsole()
        ) {
            return;
        }

        $trace = debug_backtrace();
        $caller = $trace[1];

        dump('-----');
        dump('Caller: '.$caller['file'].':'.$caller['function'].':'.$caller['line']);
        dump(...$args);
    }
}