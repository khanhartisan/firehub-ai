<?php

namespace App\Enums;

use App\Contracts\DescribableEnum;

enum KnowledgeLevel: string implements DescribableEnum
{
    case BEGINNER = 'beginner';
    case NOVICE = 'novice';
    case INTERMEDIATE = 'intermediate';
    case ADVANCED = 'advanced';
    case EXPERT = 'expert';

    public static function describe(DescribableEnum $enum): string
    {
        return match ($enum) {
            self::BEGINNER => 'New to the topic and needs simple, foundational explanations.',
            self::NOVICE => 'Has limited familiarity and benefits from guided, practical context.',
            self::INTERMEDIATE => 'Understands core concepts and is ready for deeper application details.',
            self::ADVANCED => 'Strong domain understanding and expects nuanced, technical depth.',
            self::EXPERT => 'Highly specialized audience seeking precise, high-level insights and edge cases.',
        };
    }
}