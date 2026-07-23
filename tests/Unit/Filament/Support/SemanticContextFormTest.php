<?php

namespace Tests\Unit\Filament\Support;

use App\Contracts\CommonData\AudienceContext;
use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Model\Client\Context as ClientContext;
use App\Contracts\Model\HitlPlatform\Context as HitlPlatformContext;
use App\Enums\Country;
use App\Enums\KnowledgeLevel;
use App\Filament\Support\SchemaFormFieldsFromJsonSchema;
use App\Filament\Support\SemanticContextForm;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Tests\TestCase;

class SemanticContextFormTest extends TestCase
{
    public function test_round_trips_client_context_values_and_custom_fields(): void
    {
        $context = (new ClientContext)
            ->setName('Acme')
            ->setIndustry('Technology')
            ->setNiches(['saas', 'b2b'])
            ->set('reviewer_notes', 'Extra reviewer guidance', 'Prefer concise answers')
            ->setAudienceContexts([
                (new AudienceContext)
                    ->setName('Developers')
                    ->setKnowledgeLevel(KnowledgeLevel::EXPERT)
                    ->setCountries([Country::US, Country::GB]),
            ]);

        $flat = SemanticContextForm::toFormState($context, ClientContext::class);

        $this->assertSame('Acme', $flat['name']);
        $this->assertSame('Technology', $flat['industry']);
        $this->assertSame(['saas', 'b2b'], $flat['niches']);
        $this->assertSame('Developers', $flat['audience_contexts'][0]['name']);
        $this->assertSame(KnowledgeLevel::EXPERT->value, $flat['audience_contexts'][0]['knowledge_level']);
        $this->assertSame([Country::US->value, Country::GB->value], $flat['audience_contexts'][0]['countries']);
        $this->assertSame([
            [
                'key' => 'reviewer_notes',
                'description' => 'Extra reviewer guidance',
                'value' => 'Prefer concise answers',
            ],
        ], $flat[SemanticContextForm::CUSTOM_FIELDS_KEY]);

        $envelope = SemanticContextForm::fromFormState($flat, ClientContext::class);
        $hydrated = ClientContext::fromArray($envelope);

        $this->assertSame('Acme', $hydrated->getNameValue());
        $this->assertSame('Technology', $hydrated->getIndustryValue());
        $this->assertSame(['saas', 'b2b'], $hydrated->getNichesValue());
        $this->assertSame('Prefer concise answers', $hydrated->getValue('reviewer_notes'));
        $this->assertSame('Extra reviewer guidance', $hydrated->getDescription('reviewer_notes'));

        $audiences = $hydrated->getAudienceContextsValue();
        $this->assertCount(1, $audiences);

        $audience = AudienceContext::fromArray($audiences[0]);
        $this->assertSame('Developers', $audience->getNameValue());
        $this->assertSame(KnowledgeLevel::EXPERT->value, $audience->getKnowledgeLevelValue());
        $this->assertSame([Country::US->value, Country::GB->value], $audience->getCountriesValue());
    }

    public function test_empty_schema_still_builds_custom_fields_ui(): void
    {
        $components = SemanticContextForm::components(new SemanticContext);

        $this->assertCount(1, $components);
        $this->assertInstanceOf(Section::class, $components[0]);

        $fields = SemanticContextForm::fields(new SemanticContext);
        $this->assertCount(1, $fields);
        $this->assertInstanceOf(Repeater::class, $fields[0]);
        $this->assertSame(SemanticContextForm::CUSTOM_FIELDS_KEY, $fields[0]->getName());
    }

    public function test_builds_section_when_schema_has_fields(): void
    {
        $components = SemanticContextForm::components(ClientContext::class, heading: 'Brand context');

        $this->assertCount(1, $components);
        $this->assertInstanceOf(Section::class, $components[0]);
    }

    public function test_predefined_fields_use_textarea_by_default_for_strings(): void
    {
        $fields = SchemaFormFieldsFromJsonSchema::make([
            'name' => (new JsonSchemaTypeFactory)->string(),
        ]);

        $this->assertInstanceOf(Textarea::class, $fields[0]);
    }

    public function test_string_arrays_use_textarea_by_default(): void
    {
        $factory = new JsonSchemaTypeFactory;
        $fields = SchemaFormFieldsFromJsonSchema::make([
            'guidelines' => $factory->array()->items($factory->string()),
        ]);

        $this->assertInstanceOf(Textarea::class, $fields[0]);
    }

    public function test_schema_fields_keep_specialized_inputs_for_enums(): void
    {
        $fields = SchemaFormFieldsFromJsonSchema::make(
            (new AudienceContext)->toJsonSchema(new JsonSchemaTypeFactory)
        );

        $byName = collect($fields)->filter(fn ($field) => method_exists($field, 'getName'))
            ->keyBy(fn ($field) => $field->getName());

        $this->assertInstanceOf(Textarea::class, $byName['description']);
        $this->assertInstanceOf(Select::class, $byName['countries']);
        $this->assertInstanceOf(Textarea::class, $byName['name']);
    }

    public function test_hitl_platform_context_builds_locked_fields_and_custom_repeater(): void
    {
        $fields = SemanticContextForm::fields(new HitlPlatformContext);

        $sections = collect($fields)->filter(fn ($field) => $field instanceof Section);
        $this->assertGreaterThanOrEqual(3, $sections->count());

        $custom = collect($fields)->first(fn ($field) => $field instanceof Repeater);
        $this->assertInstanceOf(Repeater::class, $custom);
        $this->assertSame(SemanticContextForm::CUSTOM_FIELDS_KEY, $custom->getName());
    }
}
