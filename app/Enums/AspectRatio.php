<?php

namespace App\Enums;

use App\Contracts\DescribableEnum;

enum AspectRatio: string implements DescribableEnum
{
    case FREE = 'free';
    case SQUARE = '1:1';
    case LANDSCAPE_STANDARD = '4:3';
    case LANDSCAPE_WIDE = '16:9';
    case PORTRAIT_STANDARD = '3:4';
    case PORTRAIT_TALL = '9:16';
    case CLASSIC_FILM = '3:2';
    case CINEMATIC = '21:9';

    public static function describe(DescribableEnum $enum): string
    {
        return match ($enum) {
            self::FREE => 'No fixed ratio; adapts to the source image dimensions.',
            self::SQUARE => 'Square format (1:1), ideal for profile and social thumbnails.',
            self::LANDSCAPE_STANDARD => 'Standard landscape (4:3), suitable for general web visuals.',
            self::LANDSCAPE_WIDE => 'Wide landscape (16:9), common for banners and video covers.',
            self::PORTRAIT_STANDARD => 'Standard portrait (3:4), good for editorial and poster layouts.',
            self::PORTRAIT_TALL => 'Tall portrait (9:16), optimized for story and short-form feeds.',
            self::CLASSIC_FILM => 'Classic photo ratio (3:2), commonly used in photography.',
            self::CINEMATIC => 'Ultra-wide cinematic frame (21:9), for dramatic panoramic compositions.',
            default => 'Unknown aspect ratio.',
        };
    }
}