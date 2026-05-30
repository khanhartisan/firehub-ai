<?php

namespace Tests\Unit\Utils;

use App\Utils\StructuredDataFromSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StructuredDataFromSchemaTest extends TestCase
{
    private JsonSchemaTypeFactory $schema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema = new JsonSchemaTypeFactory;
    }

    public function test_uses_default_when_key_is_missing(): void
    {
        $properties = [
            'name' => $this->schema->string()->default('anonymous'),
        ];

        $result = StructuredDataFromSchema::fromSchema($properties, []);

        $this->assertSame(['name' => 'anonymous'], $result);
    }

    public function test_returns_null_for_missing_nullable_field(): void
    {
        $properties = [
            'note' => $this->schema->string()->nullable(),
        ];

        $result = StructuredDataFromSchema::fromSchema($properties, []);

        $this->assertSame(['note' => null], $result);
    }

    public static function emptyValueForMissingKeyProvider(): array
    {
        return [
            'string' => ['string', ''],
            'integer' => ['integer', 0],
            'number' => ['number', 0.0],
            'boolean' => ['boolean', false],
            'array' => ['array', []],
        ];
    }

    #[DataProvider('emptyValueForMissingKeyProvider')]
    public function test_uses_empty_value_for_missing_non_nullable_field(string $kind, mixed $expected): void
    {
        $type = match ($kind) {
            'string' => $this->schema->string(),
            'integer' => $this->schema->integer(),
            'number' => $this->schema->number(),
            'boolean' => $this->schema->boolean(),
            'array' => $this->schema->array(),
        };

        $result = StructuredDataFromSchema::fromSchema(['value' => $type], []);

        $this->assertSame(['value' => $expected], $result);
    }

    public function test_uses_empty_object_for_missing_nested_object(): void
    {
        $properties = [
            'meta' => $this->schema->object([
                'key' => $this->schema->string(),
            ]),
        ];

        $result = StructuredDataFromSchema::fromSchema($properties, []);

        $this->assertEquals(['meta' => (object) ['key' => '']], $result);
    }

    public function test_preserves_present_scalar_values(): void
    {
        $properties = [
            'name' => $this->schema->string(),
            'count' => $this->schema->integer(),
            'ratio' => $this->schema->number(),
            'active' => $this->schema->boolean(),
        ];

        $result = StructuredDataFromSchema::fromSchema($properties, [
            'name' => 'Acme',
            'count' => 3,
            'ratio' => 1.5,
            'active' => true,
        ]);

        $this->assertSame([
            'name' => 'Acme',
            'count' => 3,
            'ratio' => 1.5,
            'active' => true,
        ], $result);
    }

    public function test_normalizes_present_null_to_empty_when_not_nullable(): void
    {
        $properties = [
            'title' => $this->schema->string(),
            'tags' => $this->schema->array(),
        ];

        $result = StructuredDataFromSchema::fromSchema($properties, [
            'title' => null,
            'tags' => null,
        ]);

        $this->assertSame([
            'title' => '',
            'tags' => [],
        ], $result);
    }

    public function test_normalizes_present_null_to_null_when_nullable(): void
    {
        $properties = [
            'title' => $this->schema->string()->nullable(),
        ];

        $result = StructuredDataFromSchema::fromSchema($properties, [
            'title' => null,
        ]);

        $this->assertSame(['title' => null], $result);
    }

    public function test_shapes_nested_object_from_array_input(): void
    {
        $properties = [
            'author' => $this->schema->object([
                'name' => $this->schema->string(),
                'score' => $this->schema->integer(),
            ]),
        ];

        $result = StructuredDataFromSchema::fromSchema($properties, [
            'author' => [
                'name' => 'Ada',
                'score' => 10,
            ],
        ]);

        $this->assertEquals([
            'author' => (object) [
                'name' => 'Ada',
                'score' => 10,
            ],
        ], $result);
    }

    public function test_shapes_nested_object_with_defaults_for_missing_inner_keys(): void
    {
        $properties = [
            'author' => $this->schema->object([
                'name' => $this->schema->string(),
                'score' => $this->schema->integer(),
            ]),
        ];

        $result = StructuredDataFromSchema::fromSchema($properties, [
            'author' => [
                'name' => 'Ada',
            ],
        ]);

        $this->assertEquals([
            'author' => (object) [
                'name' => 'Ada',
                'score' => 0,
            ],
        ], $result);
    }

    public function test_returns_empty_nested_object_when_value_is_not_an_array(): void
    {
        $properties = [
            'author' => $this->schema->object([
                'name' => $this->schema->string(),
            ]),
        ];

        $result = StructuredDataFromSchema::fromSchema($properties, [
            'author' => 'invalid',
        ]);

        $this->assertEquals([
            'author' => (object) ['name' => ''],
        ], $result);
    }

    public function test_reindexes_array_without_item_schema(): void
    {
        $properties = [
            'ids' => $this->schema->array(),
        ];

        $result = StructuredDataFromSchema::fromSchema($properties, [
            'ids' => [2 => 'a', 5 => 'b'],
        ]);

        $this->assertSame(['ids' => ['a', 'b']], $result);
    }

    public function test_normalizes_array_items_using_item_schema(): void
    {
        $properties = [
            'labels' => $this->schema->array()->items(
                $this->schema->string()
            ),
        ];

        $result = StructuredDataFromSchema::fromSchema($properties, [
            'labels' => ['one', null, 'three'],
        ]);

        $this->assertSame(['labels' => ['one', '', 'three']], $result);
    }

    public function test_returns_empty_array_when_value_is_not_an_array(): void
    {
        $properties = [
            'labels' => $this->schema->array()->items(
                $this->schema->string()
            ),
        ];

        $result = StructuredDataFromSchema::fromSchema($properties, [
            'labels' => 'not-an-array',
        ]);

        $this->assertSame(['labels' => []], $result);
    }

    public function test_only_includes_keys_defined_in_schema(): void
    {
        $properties = [
            'name' => $this->schema->string(),
        ];

        $result = StructuredDataFromSchema::fromSchema($properties, [
            'name' => 'Acme',
            'extra' => 'ignored',
        ]);

        $this->assertSame(['name' => 'Acme'], $result);
    }
}
