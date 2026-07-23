<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Illuminate\JsonSchema\Types\ArrayType;
use Illuminate\JsonSchema\Types\BooleanType;
use Illuminate\JsonSchema\Types\IntegerType;
use Illuminate\JsonSchema\Types\NumberType;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\StringType;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;

/**
 * Build Filament form components from a Laravel JSON Schema property map.
 */
final class SchemaFormFieldsFromJsonSchema
{
    /**
     * @param  array<string, Type>  $properties
     * @return array<int, Component|Field>
     */
    public static function make(array $properties, string $statePathPrefix = ''): array
    {
        $fields = [];

        foreach ($properties as $key => $type) {
            if (! $type instanceof Type) {
                continue;
            }

            $path = $statePathPrefix === '' ? (string) $key : $statePathPrefix.'.'.$key;
            $fields[] = self::fieldForType($type, $path, (string) $key);
        }

        return $fields;
    }

    /**
     * @return Component|Field
     */
    private static function fieldForType(Type $type, string $path, string $key): Component|Field
    {
        /** @var array<string, mixed> $attributes */
        $attributes = (fn (): array => get_object_vars($type))->call($type);

        return match ($type::class) {
            StringType::class => self::stringField($path, $key, $attributes),
            IntegerType::class => self::integerField($path, $key, $attributes),
            NumberType::class => self::numberField($path, $key, $attributes),
            BooleanType::class => self::booleanField($path, $key, $attributes),
            ObjectType::class => self::objectField($path, $key, $attributes),
            ArrayType::class => self::arrayField($path, $key, $attributes),
            default => self::jsonFallbackField($path, $key, $attributes),
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function stringField(string $path, string $key, array $attributes): Field
    {
        if (isset($attributes['enum']) && is_array($attributes['enum']) && $attributes['enum'] !== []) {
            $field = Select::make($path)
                ->options(collect($attributes['enum'])->mapWithKeys(
                    fn (mixed $value): array => [(string) $value => (string) $value]
                )->all());

            return self::configureField($field, $key, $attributes);
        }

        $field = TextInput::make($path);

        if (self::looksSensitive($key)) {
            $field->password()->revealable();
        }

        if (isset($attributes['minLength'])) {
            $field->minLength((int) $attributes['minLength']);
        }

        if (isset($attributes['maxLength'])) {
            $field->maxLength((int) $attributes['maxLength']);
        }

        if (isset($attributes['format'])) {
            match ($attributes['format']) {
                'email' => $field->email(),
                'uri', 'url' => $field->url(),
                default => null,
            };
        }

        return self::configureField($field, $key, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function integerField(string $path, string $key, array $attributes): Field
    {
        $field = TextInput::make($path)
            ->numeric()
            ->integer();

        if (isset($attributes['minimum'])) {
            $field->minValue((int) $attributes['minimum']);
        }

        if (isset($attributes['maximum'])) {
            $field->maxValue((int) $attributes['maximum']);
        }

        return self::configureField($field, $key, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function numberField(string $path, string $key, array $attributes): Field
    {
        $field = TextInput::make($path)->numeric();

        if (isset($attributes['minimum'])) {
            $field->minValue((float) $attributes['minimum']);
        }

        if (isset($attributes['maximum'])) {
            $field->maxValue((float) $attributes['maximum']);
        }

        return self::configureField($field, $key, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function booleanField(string $path, string $key, array $attributes): Field
    {
        return self::configureField(Toggle::make($path), $key, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function objectField(string $path, string $key, array $attributes): Component|Field
    {
        $properties = $attributes['properties'] ?? [];

        if (! is_array($properties) || $properties === []) {
            return self::jsonFallbackField($path, $key, $attributes);
        }

        return Fieldset::make(self::labelFor($key, $attributes))
            ->schema(self::make($properties, $path))
            ->columnSpanFull();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function arrayField(string $path, string $key, array $attributes): Component|Field
    {
        $items = $attributes['items'] ?? null;

        if ($items instanceof StringType
            || $items instanceof IntegerType
            || $items instanceof NumberType
        ) {
            $field = TagsInput::make($path);

            return self::configureField($field, $key, $attributes);
        }

        if ($items instanceof ObjectType) {
            /** @var array<string, mixed> $itemAttributes */
            $itemAttributes = (fn (): array => get_object_vars($items))->call($items);
            $itemProperties = $itemAttributes['properties'] ?? [];

            if (is_array($itemProperties) && $itemProperties !== []) {
                $field = Repeater::make($path)
                    ->schema(self::make($itemProperties))
                    ->columnSpanFull();

                return self::configureField($field, $key, $attributes);
            }
        }

        return self::jsonFallbackField($path, $key, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function jsonFallbackField(string $path, string $key, array $attributes): Field
    {
        return self::configureField(
            JsonField::make($path, $attributes['description'] ?? 'JSON value.'),
            $key,
            $attributes,
            applyHelperText: false,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function configureField(
        Field $field,
        string $key,
        array $attributes,
        bool $applyHelperText = true,
    ): Field {
        $field->label(self::labelFor($key, $attributes));

        if ($applyHelperText && filled($attributes['description'] ?? null)) {
            $field->helperText((string) $attributes['description']);
        }

        if (($attributes['required'] ?? false) === true) {
            $field->required();
        }

        if (array_key_exists('default', $attributes) && $attributes['default'] !== null) {
            $field->default($attributes['default']);
        }

        return $field;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function labelFor(string $key, array $attributes): string
    {
        if (filled($attributes['title'] ?? null)) {
            return (string) $attributes['title'];
        }

        return Str::headline($key);
    }

    private static function looksSensitive(string $key): bool
    {
        return (bool) preg_match('/(?:api[_-]?key|password|secret|token|credential)/i', $key);
    }
}
