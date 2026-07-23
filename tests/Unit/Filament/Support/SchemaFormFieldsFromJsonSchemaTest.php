<?php

namespace Tests\Unit\Filament\Support;

use App\Filament\Support\SchemaFormFieldsFromJsonSchema;
use App\Services\HitlGateway\HitlPlatformManagerDrivers\FiretasksPlatformManager\Config as FiretasksConfig;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Tests\TestCase;

class SchemaFormFieldsFromJsonSchemaTest extends TestCase
{
    public function test_builds_fields_from_firetasks_config_schema(): void
    {
        $schema = (new FiretasksConfig)->toJsonSchema(new JsonSchemaTypeFactory);
        $fields = SchemaFormFieldsFromJsonSchema::make($schema, 'config');

        $this->assertCount(5, $fields);

        $byName = collect($fields)->keyBy(fn ($field) => $field->getName());

        $this->assertInstanceOf(Textarea::class, $byName['config.base_url']);
        $this->assertTrue($byName['config.base_url']->isRequired());

        $this->assertInstanceOf(TextInput::class, $byName['config.api_key']);
        $this->assertTrue($byName['config.api_key']->isRequired());

        $this->assertInstanceOf(TextInput::class, $byName['config.folder_id']);
        $this->assertTrue($byName['config.folder_id']->isRequired());

        $this->assertInstanceOf(TextInput::class, $byName['config.default_responsible_user_id']);
        $this->assertTrue($byName['config.default_responsible_user_id']->isRequired());

        $this->assertInstanceOf(Textarea::class, $byName['config.note']);
        $this->assertFalse($byName['config.note']->isRequired());
    }

    public function test_maps_common_json_schema_types(): void
    {
        $factory = new JsonSchemaTypeFactory;

        $fields = SchemaFormFieldsFromJsonSchema::make([
            'enabled' => $factory->boolean()->required(),
            'mode' => $factory->string()->enum(['a', 'b'])->required(),
            'tags' => $factory->array()->items($factory->string()),
            'profile' => $factory->object([
                'display_name' => $factory->string()->required(),
            ]),
            'items' => $factory->array()->items($factory->object([
                'label' => $factory->string()->required(),
            ])),
            'secret' => $factory->string(),
        ], 'config');

        $this->assertInstanceOf(Toggle::class, $fields[0]);
        $this->assertSame('config.enabled', $fields[0]->getName());

        $this->assertInstanceOf(Select::class, $fields[1]);
        $this->assertSame('config.mode', $fields[1]->getName());

        $this->assertInstanceOf(Textarea::class, $fields[2]);
        $this->assertSame('config.tags', $fields[2]->getName());

        $this->assertInstanceOf(Fieldset::class, $fields[3]);

        $this->assertInstanceOf(Repeater::class, $fields[4]);
        $this->assertSame('config.items', $fields[4]->getName());

        $this->assertInstanceOf(TextInput::class, $fields[5]);
        $this->assertSame('config.secret', $fields[5]->getName());
    }

    public function test_returns_empty_array_for_empty_schema(): void
    {
        $this->assertSame([], SchemaFormFieldsFromJsonSchema::make([]));
    }

    public function test_numeric_arrays_still_use_tags_input(): void
    {
        $factory = new JsonSchemaTypeFactory;
        $fields = SchemaFormFieldsFromJsonSchema::make([
            'ids' => $factory->array()->items($factory->integer()),
        ]);

        $this->assertInstanceOf(TagsInput::class, $fields[0]);
    }
}
