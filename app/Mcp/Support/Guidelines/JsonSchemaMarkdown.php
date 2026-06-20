<?php

namespace App\Mcp\Support\Guidelines;

use App\Contracts\Mcp\StructuredMcpResource;
use App\Contracts\ProvidesJsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\Type;

/**
 * Render FlyCMS JSON Schema property maps as MCP guideline markdown tables.
 */
final class JsonSchemaMarkdown
{
    /**
     * @param  class-string<ProvidesJsonSchema>  $createMutationClass
     * @param  class-string<ProvidesJsonSchema>|null  $updateMutationClass
     * @param  list<string>  $exclude
     */
    public static function mutationFieldTable(
        string $createMutationClass,
        ?string $updateMutationClass = null,
        array $exclude = [],
        string $heading = 'Fields overview',
    ): string {
        $factory = new JsonSchemaTypeFactory;

        /** @var ProvidesJsonSchema $createMutation */
        $createMutation = new $createMutationClass;
        $createProperties = $createMutation->toJsonSchema($factory);

        $updateProperties = [];
        if ($updateMutationClass !== null) {
            /** @var ProvidesJsonSchema $updateMutation */
            $updateMutation = new $updateMutationClass;
            $updateProperties = $updateMutation->toJsonSchema($factory);
        }

        $rows = [];

        $updatePropertyKeys = array_keys($updateProperties);
        $hasUpdateSchema = $updateMutationClass !== null;

        foreach ($createProperties as $field => $type) {
            if (in_array($field, $exclude, true)) {
                continue;
            }

            $rows[$field] = [
                'create' => $type->toArray(),
                'update' => isset($updateProperties[$field]) ? $updateProperties[$field]->toArray() : null,
                'available_on_update' => ! $hasUpdateSchema || in_array($field, $updatePropertyKeys, true),
            ];
        }

        foreach ($updateProperties as $field => $type) {
            if (in_array($field, $exclude, true) || isset($rows[$field])) {
                continue;
            }

            $rows[$field] = [
                'create' => null,
                'update' => $type->toArray(),
                'available_on_update' => true,
            ];
        }

        if ($rows === []) {
            return '';
        }

        $lines = [
            "## {$heading}",
            '',
            '| Field | Format | Required on create | Available on update | Notes |',
            '|-------|--------|--------------------|--------------------|-------|',
        ];

        foreach ($rows as $field => $schemas) {
            $createSchema = $schemas['create'];
            $updateSchema = $schemas['update'];
            $referenceSchema = $updateSchema ?? $createSchema ?? [];

            $lines[] = sprintf(
                '| `%s` | %s | %s | %s | %s |',
                self::escapeTableCell($field),
                self::escapeTableCell(self::inferFormat($referenceSchema)),
                self::escapeTableCell(self::requiredOnCreateLabel($createSchema)),
                self::escapeTableCell($schemas['available_on_update'] ? 'Yes' : 'No'),
                self::escapeTableCell(self::notes($createSchema, $updateSchema)),
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param  class-string<StructuredMcpResource>  $resourceClass
     * @param  list<string>  $exclude
     */
    public static function resourceOutputTable(
        string $resourceClass,
        array $exclude = [],
        string $heading = 'Response fields',
    ): string {
        $factory = new JsonSchemaTypeFactory;
        $properties = $resourceClass::getMcpOutputSchema($factory);

        $rows = [];

        foreach ($properties as $field => $type) {
            if (in_array($field, $exclude, true)) {
                continue;
            }

            if (! $type instanceof Type) {
                continue;
            }

            $schema = $type->toArray();
            $rows[] = sprintf(
                '| `%s` | %s | %s |',
                self::escapeTableCell($field),
                self::escapeTableCell(self::inferFormat($schema)),
                self::escapeTableCell(self::descriptionWithConstraints($schema)),
            );
        }

        if ($rows === []) {
            return '';
        }

        return implode("\n", [
            "## {$heading}",
            '',
            '| Field | Format | Description |',
            '|-------|--------|-------------|',
            ...$rows,
        ]);
    }

    /**
     * @param  array<string, Type>  $metaProperties
     * @param  list<string>  $keys
     */
    public static function metaFallbackTable(
        array $metaProperties,
        array $keys,
        string $heading = 'Website meta fallbacks',
        ?string $intro = null,
    ): string {
        $rows = [];

        foreach ($keys as $key) {
            $type = $metaProperties[$key] ?? null;

            if (! $type instanceof Type) {
                continue;
            }

            $schema = $type->toArray();
            $rows[] = sprintf(
                '| `%s` | %s | %s |',
                self::escapeTableCell($key),
                self::escapeTableCell(self::inferFormat($schema)),
                self::escapeTableCell(self::descriptionWithConstraints($schema)),
            );
        }

        if ($rows === []) {
            return '';
        }

        $lines = [
            "## {$heading}",
            '',
        ];

        if ($intro !== null && $intro !== '') {
            $lines[] = $intro;
            $lines[] = '';
        }

        return implode("\n", [
            ...$lines,
            '| Meta key | Format | Description |',
            '|----------|--------|-------------|',
            ...$rows,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $createSchema
     */
    private static function requiredOnCreateLabel(?array $createSchema): string
    {
        if ($createSchema === null) {
            return 'No';
        }

        return self::isNullable($createSchema) ? 'No' : 'Yes';
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private static function isNullable(array $schema): bool
    {
        $type = $schema['type'] ?? 'string';

        if (! is_array($type)) {
            return false;
        }

        return in_array('null', $type, true);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private static function inferFormat(array $schema): string
    {
        $description = strtolower((string) ($schema['description'] ?? ''));
        $type = $schema['type'] ?? 'string';
        $primaryType = is_array($type)
            ? array_values(array_filter($type, fn (mixed $value): bool => $value !== 'null'))[0] ?? 'string'
            : $type;

        if (str_contains($description, 'liquid template')) {
            return 'Liquid template';
        }

        if (str_contains($description, 'html')) {
            return 'HTML';
        }

        if (str_contains($description, 'kebab-case')) {
            return 'kebab-case';
        }

        return match ($primaryType) {
            'boolean' => 'Boolean',
            'integer' => 'Integer',
            'number' => 'Number',
            'array' => 'Array',
            'object' => 'Object',
            default => 'Plain text',
        };
    }

    /**
     * @param  array<string, mixed>|null  $createSchema
     * @param  array<string, mixed>|null  $updateSchema
     */
    private static function notes(?array $createSchema, ?array $updateSchema): string
    {
        $schema = $updateSchema ?? $createSchema ?? [];

        return self::descriptionWithConstraints($schema);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private static function descriptionWithConstraints(array $schema): string
    {
        $parts = [];

        $description = trim((string) ($schema['description'] ?? ''));
        if ($description !== '') {
            $parts[] = self::stripResourcePointer($description);
        }

        if (isset($schema['maxLength'])) {
            $parts[] = 'max '.$schema['maxLength'].' characters';
        }

        if (isset($schema['default'])) {
            $parts[] = 'default '.json_encode($schema['default']);
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && $schema['enum'] !== []) {
            $parts[] = 'enum: '.implode(', ', array_map(strval(...), $schema['enum']));
        }

        return implode('. ', $parts);
    }

    private static function stripResourcePointer(string $description): string
    {
        return trim(preg_replace('/\s*\.?\s*See resource:.*$/i', '', $description) ?? $description);
    }

    private static function escapeTableCell(string $value): string
    {
        return str_replace('|', '\\|', $value);
    }
}
