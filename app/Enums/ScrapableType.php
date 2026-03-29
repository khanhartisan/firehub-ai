<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ScrapableType: int implements HasLabel
{
    case UNCLASSIFIED = 0;
    case PAGE = 1;
    case IMAGE = 2;
    case VIDEO = 3;
    case DOCUMENT = 4;
    case UNKNOWN = 5;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::UNCLASSIFIED => 'Unclassified',
            self::PAGE => 'Page',
            self::IMAGE => 'Image',
            self::VIDEO => 'Video',
            self::DOCUMENT => 'Document',
            self::UNKNOWN => 'Unknown',
        };
    }
}
