<?php

namespace App\Utils;

use Illuminate\JsonSchema\Types\ArrayType;
use Illuminate\JsonSchema\Types\BooleanType;
use Illuminate\JsonSchema\Types\IntegerType;
use Illuminate\JsonSchema\Types\NumberType;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\StringType;
use Illuminate\JsonSchema\Types\Type;

/**
 * Shape raw data to match a Laravel JSON Schema property map (defaults, nullability, nested types).
 */
final class StructuredDataFromSchema
{
    /**
     * @param  array<string, Type>  $properties
     */
    public static function fromSchema(array $properties, array $data): array
    {
        $structured = [];

        foreach ($properties as $key => $type) {
            $structured[$key] = self::valueForType(
                $type,
                array_key_exists($key, $data) ? $data[$key] : null,
                array_key_exists($key, $data)
            );
        }

        return $structured;
    }

    private static function valueForType(Type $type, mixed $value, bool $present): mixed
    {
        /** @var array<string, mixed> $attributes */
        $attributes = (fn () => get_object_vars($type))->call($type);
        $nullable = ($attributes['nullable'] ?? false) === true;

        if (! $present) {
            if ($attributes['default'] !== null) {
                return $attributes['default'];
            }

            return $nullable ? null : self::emptyValueForType($type, $attributes);
        }

        if ($value === null) {
            return $nullable ? null : self::emptyValueForType($type, $attributes);
        }

        return self::normalizeValueForType($type, $value, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function normalizeValueForType(Type $type, mixed $value, array $attributes): mixed
    {
        return match ($type::class) {
            ObjectType::class => self::normalizeObjectValue($value, $attributes),
            ArrayType::class => self::normalizeArrayValue($value, $attributes),
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function normalizeObjectValue(mixed $value, array $attributes): object
    {
        $properties = $attributes['properties'] ?? [];

        if ($properties === []) {
            if (is_array($value)) {
                return (object) $value;
            }

            if (is_object($value)) {
                return $value;
            }

            return (object) [];
        }

        return (object) self::fromSchema(
            $properties,
            is_array($value) ? $value : []
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function normalizeArrayValue(mixed $value, array $attributes): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = $attributes['items'] ?? null;

        if (! $items instanceof Type) {
            return array_values($value);
        }

        return array_map(
            fn (mixed $item): mixed => self::valueForType($items, $item, true),
            array_values($value)
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function emptyValueForType(Type $type, array $attributes): mixed
    {
        return match ($type::class) {
            ObjectType::class => (object) self::fromSchema($attributes['properties'] ?? [], []),
            ArrayType::class => [],
            StringType::class => '',
            IntegerType::class => 0,
            NumberType::class => 0.0,
            BooleanType::class => false,
            default => null,
        };
    }
}
