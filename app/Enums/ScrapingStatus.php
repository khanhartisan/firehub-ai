<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ScrapingStatus: int implements HasLabel
{
    case PENDING = 0;
    case QUEUED = 1;
    case FETCHING = 2;
    case PROCESSING = 3;
    case SUCCESS = 4;
    case FAILED = 5;
    case TIMEOUT = 6;
    case BLOCKED = 7;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::QUEUED => 'Queued',
            self::FETCHING => 'Fetching',
            self::PROCESSING => 'Processing',
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
            self::TIMEOUT => 'Timeout',
            self::BLOCKED => 'Blocked',
        };
    }
}