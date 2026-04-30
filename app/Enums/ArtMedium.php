<?php

namespace App\Enums;

use App\Contracts\DescribableEnum;

enum ArtMedium: string implements DescribableEnum
{
    case PHOTOGRAPHY = 'photography';
    case ILLUSTRATION_2D = 'illustration_2d';
    case ILLUSTRATION_3D = 'illustration_3d';

    public static function describe(DescribableEnum $enum): string
    {
        return match ($enum) {
            self::PHOTOGRAPHY => 'Photo-real imagery that mimics camera-captured scenes, lighting, and textures.',
            self::ILLUSTRATION_2D => 'Flat or painterly 2D artwork, ideal for stylized concepts, diagrams, and editorial visuals.',
            self::ILLUSTRATION_3D => 'Rendered 3D artwork with depth, perspective, and volumetric form for product-like or cinematic visuals.',
            default => 'Unknown art medium.',
        };
    }
}