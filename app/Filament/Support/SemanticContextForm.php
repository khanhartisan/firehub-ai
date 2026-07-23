<?php

namespace App\Filament\Support;

use App\Contracts\CommonData\SemanticContext;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\ArrayType;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Build Filament form UI for SemanticContext values, converting to/from the
 * stored {description, value, weight} envelope shape.
 *
 * Pre-defined schema fields expose a locked name/description and an editable
 * value. Extra custom fields can be added freely (schema-free).
 */
final class SemanticContextForm
{
    public const CUSTOM_FIELDS_KEY = '_custom';

    /**
     * @return array<int, Component|Field>
     */
    public static function components(
        SemanticContext|string $context,
        string $statePath = 'context',
        string $heading = 'Context',
        ?string $description = null,
    ): array {
        $template = self::resolveTemplate($context);

        return [
            Section::make($heading)
                ->description($description ?? 'Pre-defined fields keep a fixed name and description. Add custom fields for anything else.')
                ->schema([
                    Group::make(self::fields($template))
                        ->statePath($statePath)
                        ->formatStateUsing(fn (mixed $state): array => self::toFormState($state, $template))
                        ->dehydrateStateUsing(fn (mixed $state): array => self::fromFormState(
                            is_array($state) ? $state : [],
                            $template,
                        ))
                        ->columns(1)
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Component|Field>
     */
    public static function fields(SemanticContext $context, string $statePathPrefix = ''): array
    {
        $schema = $context->toJsonSchema(new JsonSchemaTypeFactory);
        $reservedKeys = array_map('strval', array_keys($schema));
        $fields = [];

        foreach ($schema as $key => $type) {
            if (! $type instanceof Type) {
                continue;
            }

            $key = (string) $key;
            $path = $statePathPrefix === '' ? $key : $statePathPrefix.'.'.$key;
            $fields[] = self::predefinedFieldset($key, $path, $type);
        }

        $fields[] = self::customFieldsRepeater($reservedKeys, $statePathPrefix);

        return $fields;
    }

    /**
     * Flatten a SemanticContext envelope (or instance) into form-friendly values.
     *
     * @return array<string, mixed>
     */
    public static function toFormState(mixed $state, SemanticContext|string|null $template = null): array
    {
        if ($state instanceof SemanticContext) {
            $envelope = $state->toArray();
        } elseif (is_array($state)) {
            $envelope = self::looksLikeContextData($state) ? $state : [];
            if ($envelope === [] && $state !== [] && ! self::looksLikeContextData($state)) {
                // Already flat form state (e.g. livewire re-entry)
                if (array_key_exists(self::CUSTOM_FIELDS_KEY, $state)) {
                    $template = $template !== null ? self::resolveTemplate($template) : null;

                    if ($template !== null && blank($state['__locked'] ?? null)) {
                        $state['__locked'] = self::lockedMetaForTemplate($template);
                    }

                    return $state;
                }
            }
        } else {
            $envelope = [];
        }

        $template = $template !== null ? self::resolveTemplate($template) : null;
        $schema = $template !== null
            ? $template->toJsonSchema(new JsonSchemaTypeFactory)
            : [];
        $schemaKeys = array_map('strval', array_keys($schema));

        $form = [
            '__locked' => $template !== null ? self::lockedMetaForTemplate($template) : [],
        ];
        $custom = [];

        foreach ($envelope as $key => $entry) {
            $key = (string) $key;

            if (! is_array($entry) || ! array_key_exists('value', $entry)) {
                continue;
            }

            $value = self::unwrapValue($entry['value']);

            if ($schemaKeys !== [] && in_array($key, $schemaKeys, true)) {
                $form[$key] = $value;

                continue;
            }

            $custom[] = [
                'key' => $key,
                'description' => is_string($entry['description'] ?? null)
                    ? $entry['description']
                    : Str::headline($key),
                'value' => self::valueToTextarea($entry['value']),
            ];
        }

        $form[self::CUSTOM_FIELDS_KEY] = $custom;

        return $form;
    }

    /**
     * @return array<string, array{key: string, description: string}>
     */
    private static function lockedMetaForTemplate(SemanticContext $template): array
    {
        $template = $template->withEmptyFields(recursive: true, clone: true);
        $schema = $template->toJsonSchema(new JsonSchemaTypeFactory);
        $locked = [];

        foreach ($schema as $key => $type) {
            if (! $type instanceof Type) {
                continue;
            }

            $key = (string) $key;
            $locked[$key] = [
                'key' => $key,
                'description' => $template->getDescription($key)
                    ?? self::descriptionFromType($type)
                    ?? Str::headline($key),
            ];
        }

        return $locked;
    }

    /**
     * Rebuild a SemanticContext::toArray() envelope from flat form values.
     *
     * @param  array<string, mixed>  $flat
     * @return array<string, mixed>
     */
    public static function fromFormState(array $flat, SemanticContext|string $context): array
    {
        $template = self::resolveTemplate($context)->withEmptyFields(recursive: true, clone: true);
        $schema = $template->toJsonSchema(new JsonSchemaTypeFactory);
        $reservedKeys = array_map('strval', array_keys($schema));
        $envelope = [];

        unset($flat['__locked']);

        foreach ($schema as $key => $type) {
            if (! $type instanceof Type || ! array_key_exists($key, $flat)) {
                continue;
            }

            $envelope[$key] = [
                'description' => $template->getDescription((string) $key)
                    ?? self::descriptionFromType($type)
                    ?? Str::headline((string) $key),
                'value' => self::wrapValue($flat[$key], $type),
                'weight' => $template->getWeight((string) $key),
            ];
        }

        $custom = $flat[self::CUSTOM_FIELDS_KEY] ?? [];
        if (! is_array($custom)) {
            $custom = [];
        }

        $seenCustomKeys = [];

        foreach ($custom as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = trim((string) ($item['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            if (in_array($key, $reservedKeys, true)) {
                throw ValidationException::withMessages([
                    self::CUSTOM_FIELDS_KEY => "Custom field \"{$key}\" conflicts with a pre-defined field.",
                ]);
            }

            if (isset($seenCustomKeys[$key]) || array_key_exists($key, $envelope)) {
                throw ValidationException::withMessages([
                    self::CUSTOM_FIELDS_KEY => "Custom field \"{$key}\" is duplicated.",
                ]);
            }

            $seenCustomKeys[$key] = true;

            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '') {
                $description = Str::headline($key);
            }

            $envelope[$key] = [
                'description' => $description,
                'value' => self::parseTextareaValue($item['value'] ?? null),
                'weight' => null,
            ];
        }

        return $template::fromArray($envelope)->toArray();
    }

    private static function predefinedFieldset(
        string $key,
        string $path,
        Type $type,
    ): Fieldset {
        $valueComponents = SchemaFormFieldsFromJsonSchema::make(
            [$key => $type],
            self::parentPath($path, $key),
            [
                'stringsAsTextarea' => true,
            ],
        );

        foreach ($valueComponents as $component) {
            if ($component instanceof Field) {
                $component
                    ->label('Value')
                    ->helperText(null);
            }
        }

        return Fieldset::make($key)
            ->label(Str::headline($key))
            ->schema([
                TextInput::make('__locked.'.$key.'.key')
                    ->label('Field')
                    ->disabled()
                    ->dehydrated(false),
                Textarea::make('__locked.'.$key.'.description')
                    ->label('Description')
                    ->rows(2)
                    ->disabled()
                    ->dehydrated(false),
                ...$valueComponents,
            ])
            ->columns(1)
            ->columnSpanFull();
    }

    /**
     * @param  list<string>  $reservedKeys
     */
    private static function customFieldsRepeater(array $reservedKeys, string $statePathPrefix): Repeater
    {
        $path = $statePathPrefix === ''
            ? self::CUSTOM_FIELDS_KEY
            : $statePathPrefix.'.'.self::CUSTOM_FIELDS_KEY;

        return Repeater::make($path)
            ->label('Custom fields')
            ->addActionLabel('Add field')
            ->schema([
                TextInput::make('key')
                    ->label('Field')
                    ->required()
                    ->regex('/^[a-z][a-z0-9_]*$/')
                    ->validationMessages([
                        'regex' => 'Use a lowercase snake_case key (e.g. reviewer_notes).',
                    ])
                    ->rule(function () use ($reservedKeys) {
                        return function (string $attribute, mixed $value, \Closure $fail) use ($reservedKeys): void {
                            if (in_array((string) $value, $reservedKeys, true)) {
                                $fail('This field name is reserved by a pre-defined schema field.');
                            }
                        };
                    })
                    ->distinct()
                    ->maxLength(100),
                Textarea::make('description')
                    ->label('Description')
                    ->rows(2)
                    ->required()
                    ->maxLength(2000),
                Textarea::make('value')
                    ->label('Value')
                    ->rows(4)
                    ->helperText('Plain text, or JSON for arrays/objects.'),
            ])
            ->columns(1)
            ->columnSpanFull()
            ->default([]);
    }

    private static function parentPath(string $path, string $key): string
    {
        if ($path === $key) {
            return '';
        }

        $suffix = '.'.$key;
        if (str_ends_with($path, $suffix)) {
            return substr($path, 0, -strlen($suffix));
        }

        return '';
    }

    private static function valueToTextarea(mixed $value): string
    {
        if ($value instanceof SemanticContext) {
            $value = $value->toArray();
        }

        if (is_array($value)) {
            return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private static function parseTextareaValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (
            (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}'))
            || (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))
        ) {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        if (in_array(strtolower($trimmed), ['true', 'false'], true)) {
            return strtolower($trimmed) === 'true';
        }

        return $value;
    }

    private static function unwrapValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (self::looksLikeContextData($value)) {
            return self::unwrapContextData($value);
        }

        if ($value !== [] && array_is_list($value) && is_array($value[0]) && self::looksLikeContextData($value[0])) {
            return array_map(
                fn (array $item): array => self::unwrapContextData($item),
                $value,
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function unwrapContextData(array $data): array
    {
        if (! self::looksLikeContextData($data)) {
            return $data;
        }

        $flat = [];

        foreach ($data as $key => $entry) {
            if (! is_array($entry) || ! array_key_exists('value', $entry)) {
                $flat[$key] = $entry;

                continue;
            }

            $flat[$key] = self::unwrapValue($entry['value']);
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private static function looksLikeContextData(array $value): bool
    {
        if ($value === [] || array_is_list($value)) {
            return false;
        }

        foreach ($value as $entry) {
            if (! is_array($entry)
                || ! array_key_exists('description', $entry)
                || ! array_key_exists('value', $entry)
            ) {
                return false;
            }
        }

        return true;
    }

    private static function wrapValue(mixed $value, Type $type): mixed
    {
        /** @var array<string, mixed> $attributes */
        $attributes = (fn (): array => get_object_vars($type))->call($type);

        if ($type instanceof ArrayType) {
            $items = $attributes['items'] ?? null;

            if ($items instanceof ObjectType && is_array($value)) {
                return array_values(array_map(
                    fn (mixed $item): mixed => is_array($item)
                        ? self::wrapObjectValue($item, $items)
                        : $item,
                    $value,
                ));
            }

            return is_array($value) ? array_values($value) : [];
        }

        if ($type instanceof ObjectType) {
            $properties = $attributes['properties'] ?? [];

            if (! is_array($properties) || $properties === []) {
                return is_array($value) ? $value : [];
            }

            return self::wrapObjectValue(is_array($value) ? $value : [], $type);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $flat
     * @return array<string, mixed>
     */
    private static function wrapObjectValue(array $flat, ObjectType $type): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = (fn (): array => get_object_vars($type))->call($type);
        /** @var array<string, Type> $properties */
        $properties = $attributes['properties'] ?? [];
        $envelope = [];

        foreach ($properties as $key => $propertyType) {
            if (! $propertyType instanceof Type || ! array_key_exists($key, $flat)) {
                continue;
            }

            $envelope[$key] = [
                'description' => self::descriptionFromType($propertyType) ?? Str::headline((string) $key),
                'value' => self::wrapValue($flat[$key], $propertyType),
                'weight' => null,
            ];
        }

        return $envelope;
    }

    private static function descriptionFromType(Type $type): ?string
    {
        /** @var array<string, mixed> $attributes */
        $attributes = (fn (): array => get_object_vars($type))->call($type);
        $description = $attributes['description'] ?? null;

        return is_string($description) && $description !== '' ? $description : null;
    }

    private static function resolveTemplate(SemanticContext|string $context): SemanticContext
    {
        if ($context instanceof SemanticContext) {
            return $context;
        }

        if (! is_subclass_of($context, SemanticContext::class)) {
            throw new \InvalidArgumentException('Context must be a SemanticContext instance or class.');
        }

        return new $context;
    }
}
