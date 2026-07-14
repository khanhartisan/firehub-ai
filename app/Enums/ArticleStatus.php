<?php

namespace App\Enums;

use App\Contracts\DescribableEnum;

enum ArticleStatus: int implements DescribableEnum
{
    case UNREADY = 0;
    case PROCESSING = 1;
    case READY = 2;
    case PUBLISHED = 3;
    case REJECTED = 4;

    case FAILED = 5; // failed means stopped by purpose
    case ERROR = 6; // error means stopped by an unhandled error

    /**
     * The article is completed with the production process
     * just whether published
     */
    public static function completedStatuses(): array
    {
        return [
            self::READY,
            self::PUBLISHED,
        ];
    }

    public function isCompleted(): bool
    {
        return in_array($this, self::completedStatuses());
    }

    public static function describe(DescribableEnum $enum): string
    {
        return match ($enum) {
            self::UNREADY => 'Under configuration - Not yet ready',
            self::PROCESSING => 'In the article production process',
            self::READY => 'Production complete, ready to publish',
            self::PUBLISHED => 'Published successfully',
            self::REJECTED => 'Rejected and will not be published',
            self::FAILED => 'Stopped intentionally during production',
            self::ERROR => 'Stopped by an unhandled error during production',
            default => 'Unknown',
        };
    }
}
