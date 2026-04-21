<?php

namespace App\Enums;

enum KeywordStatus: int
{
    case PENDING = 1;
    case RESEARCHING = 2;
    case RESEARCHED = 3;
    case ERROR = 4;

    public static function finalStatuses(): array
    {
        return [self::RESEARCHED, self::ERROR];
    }

    public function isFinal(): bool
    {
        return in_array($this, self::finalStatuses());
    }
}