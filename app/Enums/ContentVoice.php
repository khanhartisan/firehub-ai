<?php

namespace App\Enums;

use App\Contracts\DescribableEnum;

enum ContentVoice: string implements DescribableEnum
{
    case AUTHORITATIVE = 'authoritative';
    case FRIENDLY      = 'friendly';
    case BOLD          = 'bold';
    case MINIMALIST    = 'minimalist';
    case ACADEMIC      = 'academic';
    case WITTY         = 'witty';
    case INSPIRATIONAL = 'inspirational';
    case SOPHISTICATED = 'sophisticated';

    public static function describe(DescribableEnum $enum): string
    {
        return match ($enum) {
            self::AUTHORITATIVE => 'Expert, leader, and reliable voice that builds trust and confidence.',
            self::FRIENDLY => 'Approachable, warm, and conversational voice that feels human and accessible.',
            self::BOLD => 'Provocative, courageous, and trend-setting voice that challenges assumptions.',
            self::MINIMALIST => 'Concise, efficient, and direct voice focused on clarity and brevity.',
            self::ACADEMIC => 'Research-oriented, formal voice with dense facts, rigor, and precision.',
            self::WITTY => 'Clever, humorous, and engaging voice that keeps attention through personality.',
            self::INSPIRATIONAL => 'Visionary, uplifting, and motivating voice designed to energize readers.',
            self::SOPHISTICATED => 'Elegant, refined, and premium voice suitable for high-end positioning.',
        };
    }
}