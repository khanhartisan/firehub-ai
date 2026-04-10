<?php

namespace App\Enums;

use App\Contracts\DescribableEnum;

enum IntentType: int implements DescribableEnum
{
    // Find information about something
    case INFORMATIONAL = 1;

    // Find a place to purchase a product or service
    case TRANSACTIONAL = 2;

    // Brand search or finding a specific website
    case NAVIGATIONAL = 3;

    // Investigating a product or service before making a purchase
    case COMMERCIAL = 4;

    // Find a local business or service
    case LOCAL = 5;

    // Unknown
    case UNKNOWN = 6;

    public static function describe(DescribableEnum $enum): string
    {
        return match ($enum) {
            self::INFORMATIONAL => 'Find information about something.',
            self::TRANSACTIONAL => 'Find a place to purchase a product or service.',
            self::NAVIGATIONAL => 'Brand search or finding a specific website.',
            self::COMMERCIAL => 'Investigating a product or service before making a purchase.',
            self::LOCAL => 'Find a local business or service.',
            self::UNKNOWN => 'Unknown intent.',
        };
    }
}
