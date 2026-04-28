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
        $callers = [
            $trace[1], $trace[2], $trace[3], $trace[4], $trace[5]
        ];

        dump('-----');
        foreach ($callers as $caller) {
            dump('Caller: '.$caller['file'].':'.$caller['function'].':'.$caller['line']);
        }

        dump(...$args);
    }
}