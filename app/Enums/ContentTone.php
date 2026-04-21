<?php

namespace App\Enums;

use App\Contracts\DescribableEnum;

enum ContentTone: string implements DescribableEnum
{
    case FORMAL        = 'formal';
    case CASUAL        = 'casual';
    case URGENT        = 'urgent';
    case EMPATHETIC    = 'empathetic';
    case INSTRUCTIONAL = 'instructional';
    case OPTIMISTIC    = 'optimistic';
    case CAUTIONARY    = 'cautionary';
    case OBJECTIVE     = 'objective';
    case PLAYFUL       = 'playful';
    case ASSERTIVE     = 'assertive';

    public static function describe(DescribableEnum $enum): string
    {
        return match ($enum) {
            self::FORMAL => 'Professional, polished, and structured tone suitable for credibility and clarity.',
            self::CASUAL => 'Relaxed, everyday tone using approachable and non-stiff language.',
            self::URGENT => 'Direct, time-sensitive tone that drives immediate attention and action.',
            self::EMPATHETIC => 'Compassionate, understanding tone centered on human needs and concerns.',
            self::INSTRUCTIONAL => 'Educational, step-by-step tone designed to guide and teach clearly.',
            self::OPTIMISTIC => 'Positive, future-looking tone that encourages confidence and momentum.',
            self::CAUTIONARY => 'Warning-oriented, risk-aware tone focused on prevention and protection.',
            self::OBJECTIVE => 'Neutral, unbiased, factual tone with minimal emotional framing.',
            self::PLAYFUL => 'High-energy, fun tone full of personality and lightness.',
            self::ASSERTIVE => 'Confident, decisive tone that communicates strong conviction.',
        };
    }
}