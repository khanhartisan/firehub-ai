<?php

namespace App\Enums;

use App\Contracts\DescribableEnum;

enum PublicationStatus: int implements DescribableEnum
{
    case AWAITING = 0;
    case PENDING = 1;
    case PUBLISHING = 2;
    case PUBLISHED = 3;
    case TIMEOUT = 4;
    case FAILED = 5;
    case ERROR = 6;

    public static function describe(DescribableEnum $enum): string
    {
        return match ($enum) {
            static::AWAITING => 'Awaiting for the base resource to be ready',
            static::PENDING => 'The base resource is ready, awaiting for publishing',
            static::PUBLISHING => 'In publishing process',
            static::PUBLISHED => 'Published successfully',
            static::TIMEOUT => 'Publishing process is timed out',
            static::FAILED => 'Failed to publish',
            static::ERROR => 'Got error during the publishing process',
            default => 'Unknown',
        };
    }

    public static function retriableStatuses(): array
    {
        return [
            static::TIMEOUT,
            static::FAILED,
            static::ERROR,
        ];
    }
}