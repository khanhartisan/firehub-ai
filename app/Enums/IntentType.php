<?php

namespace App\Enums;

use App\Contracts\DescribableEnum;

enum IntentType: string implements DescribableEnum
{
    // Find information about something
    case INFORMATIONAL = 'informational';

    // Find a place to purchase a product or service
    case TRANSACTIONAL = 'transactional';

    // Brand search or finding a specific website
    case NAVIGATIONAL = 'navigational';

    // Investigating a product or service before making a purchase
    case COMMERCIAL = 'commercial';

    // Find a local business or service
    case LOCAL = 'local';

    // Unknown
    case UNKNOWN = 'unknown';

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
