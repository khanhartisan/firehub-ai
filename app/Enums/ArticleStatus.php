<?php

namespace App\Enums;

enum ArticleStatus: int
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
     *
     * @return array
     */
    public static function completedStatuses(): array
    {
        return [
            static::READY,
            static::PUBLISHED,
        ];
    }

    public function isCompleted(): bool
    {
        return in_array($this, static::completedStatuses());
    }
}