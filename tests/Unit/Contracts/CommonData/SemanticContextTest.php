<?php

namespace Tests\Unit\Contracts\CommonData;

use App\Contracts\CommonData\Keyword;
use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\Illustration\IllustrationDirection;
use App\Enums\Language;
use Tests\TestCase;

class SemanticContextTest extends TestCase
{
    public function test_set_has_and_get_with_scalar_value(): void
    {
        $context = (new SemanticContext)->set('traffic', 'Monthly traffic', 1200);

        $this->assertTrue($context->has('traffic'));
        $this->assertFalse($context->has('missing'));
        $this->assertSame([
            'description' => 'Monthly traffic',
            'value' => 1200,
        ], $context->get('traffic'));
        $this->assertNull($context->get('missing'));
    }

    public function test_get_and_to_array_serialize_serializable_values(): void
    {
        $keyword = (new Keyword('ai agent'))->setLanguage(Language::EN);
        $context = (new SemanticContext)->set('seed_keyword', 'Main query seed', $keyword);

        $expectedKeyword = $keyword->toArray();

        $this->assertSame([
            'description' => 'Main query seed',
            'value' => $expectedKeyword,
        ], $context->get('seed_keyword'));

        $this->assertSame([
            'seed_keyword' => [
                'description' => 'Main query seed',
                'value' => $expectedKeyword,
                'weight' => null,
            ],
        ], $context->toArray());
    }

    public function test_from_array_hydrates_valid_rows_and_ignores_invalid_rows(): void
    {
        $context = SemanticContext::fromArray([
            'valid_scalar' => [
                'description' => 'Expected monthly signups',
                'value' => 450.5,
            ],
            'invalid_missing_value' => [
                'description' => 'Missing value key',
            ],
            'invalid_value_type' => [
                'description' => 'Bad value type',
                'value' => new \stdClass(),
            ],
            'invalid_description_type' => [
                'description' => 123,
                'value' => 'ok',
            ],
        ]);

        $this->assertTrue($context->has('valid_scalar'));
        $this->assertSame([
            'description' => 'Expected monthly signups',
            'value' => 450.5,
        ], $context->get('valid_scalar'));

        $this->assertFalse($context->has('invalid_missing_value'));
        $this->assertFalse($context->has('invalid_value_type'));
        $this->assertFalse($context->has('invalid_description_type'));
    }

    public function test_round_trip_preserves_all_supported_value_types(): void
    {
        $source = (new SemanticContext)
            ->set('content_type', 'Primary content type', 'guide')
            ->set('article_count', 'Number of articles', 12)
            ->set('confidence', 'Model confidence score', 0.87)
            ->set(
                'seed_keyword',
                'Core keyword context',
                (new Keyword('ai automation'))->setLanguage(Language::EN)
            );

        $dehydrated = $source->toArray();
        $hydrated = SemanticContext::fromArray($dehydrated);

        $this->assertSame($dehydrated, $hydrated->toArray());
    }

    public function test_round_trip_preserves_nested_array_values_when_serializable(): void
    {
        $source = (new SemanticContext)->set('composite', 'Nested metadata', [
            'label' => 'topic cluster',
            'metrics' => [
                'score' => 0.92,
                'count' => 7,
            ],
            'seed' => (new Keyword('ai workflow'))->setLanguage(Language::EN),
        ]);

        $dehydrated = $source->toArray();
        $hydrated = SemanticContext::fromArray($dehydrated);

        $this->assertSame($dehydrated, $hydrated->toArray());
    }

    public function test_set_rejects_non_serializable_nested_array_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SemanticContext)->set('bad', 'Invalid nested object', [
            'bad' => new \stdClass(),
        ]);
    }

    public function test_from_array_ignores_rows_with_non_serializable_nested_array_values(): void
    {
        $context = SemanticContext::fromArray([
            'valid' => [
                'description' => 'Good nested payload',
                'value' => ['score' => 0.7, 'label' => 'ok'],
            ],
            'invalid' => [
                'description' => 'Contains non-serializable value',
                'value' => ['bad' => new \stdClass()],
            ],
        ]);

        $this->assertTrue($context->has('valid'));
        $this->assertFalse($context->has('invalid'));
    }

    public function test_magic_getters_resolve_get_and_get_value_by_method_name(): void
    {
        $context = (new SemanticContext)
            ->set('sample_key', 'Sample description', 'sample value', 0.8)
            ->set('nested_key', 'Nested description', [
                'keyword' => (new Keyword('ai agent'))->setLanguage(Language::EN),
            ]);

        $this->assertSame([
            'description' => 'Sample description',
            'value' => 'sample value',
        ], $context->getSampleKey());

        $this->assertSame('sample value', $context->getSampleKeyValue());
        $this->assertSame('Sample description', $context->getSampleKeyDescription());
        $this->assertSame(0.8, $context->getSampleKeyWeight());
        $this->assertSame([
            'keyword' => [
                'keyword' => 'ai agent',
                'language' => 'en',
                'country' => null,
            ],
        ], $context->getNestedKeyValue());
    }

    public function test_magic_getters_return_null_for_missing_keys(): void
    {
        $context = new SemanticContext;

        $this->assertNull($context->getMissingKey());
        $this->assertNull($context->getMissingKeyValue());
        $this->assertNull($context->getMissingKeyDescription());
        $this->assertNull($context->getMissingKeyWeight());
    }

    public function test_magic_call_throws_for_non_getter_methods(): void
    {
        $this->expectException(\BadMethodCallException::class);

        (new SemanticContext)->setSampleKey('desc', 'value');
    }

    public function test_set_and_get_weight_with_entry_and_magic_getter(): void
    {
        $context = (new SemanticContext)->set('priority', 'Priority score', 'high', 0.65);

        $this->assertSame([
            'description' => 'Priority score',
            'value' => 'high',
        ], $context->get('priority'));
        $this->assertSame(0.65, $context->getWeight('priority'));
        $this->assertSame(0.65, $context->getPriorityWeight());
    }

    public function test_set_weight_updates_existing_entry_weight(): void
    {
        $context = (new SemanticContext)
            ->set('priority', 'Priority score', 'high')
            ->setWeight('priority', 0.9);

        $this->assertSame(0.9, $context->getWeight('priority'));
        $this->assertSame(0.9, $context->getPriorityWeight());
        $this->assertSame([
            'priority' => [
                'description' => 'Priority score',
                'value' => 'high',
                'weight' => 0.9,
            ],
        ], $context->toArray());
    }

    public function test_from_array_and_round_trip_preserve_weight(): void
    {
        $context = SemanticContext::fromArray([
            'priority' => [
                'description' => 'Priority score',
                'value' => 'high',
                'weight' => 0.4,
            ],
        ]);

        $this->assertSame(0.4, $context->getWeight('priority'));
        $this->assertSame(0.4, $context->getPriorityWeight());
        $this->assertSame([
            'priority' => [
                'description' => 'Priority score',
                'value' => 'high',
                'weight' => 0.4,
            ],
        ], $context->toArray());
    }

    public function test_with_empty_fields_builds_template_with_all_setters(): void
    {
        $context = (new class() extends SemanticContext {
            public function setName(string $name): static
            {
                return $this->set('name', 'Human readable name', $name);
            }

            public function setTags(array $tags): static
            {
                return $this->set('tags', 'List of tags', $tags);
            }

            public function setOptionalNote(?string $note): static
            {
                return $this->set('optional_note', 'Optional extra note', $note);
            }
        })->withEmptyFields();

        $this->assertSame([
            'name' => [
                'description' => 'Human readable name',
                'value' => '',
                'weight' => null,
            ],
            'optional_note' => [
                'description' => 'Optional extra note',
                'value' => null,
                'weight' => null,
            ],
            'tags' => [
                'description' => 'List of tags',
                'value' => [],
                'weight' => null,
            ],
        ], $context->toArray());
    }

    public function test_with_empty_fields_recursively_builds_nested_semantic_contexts_by_default(): void
    {
        $context = (new IllustrationDirection)->withEmptyFields();

        $this->assertIsArray($context->getConceptContextValue());
        $this->assertArrayHasKey('logline', $context->getConceptContextValue());
    }

    public function test_with_empty_fields_can_disable_recursive_nested_contexts(): void
    {
        $context = (new IllustrationDirection)->withEmptyFields(false);

        $this->assertNull($context->getConceptContextValue());
    }

    public function test_constructor_executes_all_boot_methods_before_loading_data(): void
    {
        $context = new class() extends SemanticContext {
            public int $bootCount = 0;

            protected function bootAllowedKeys(): void
            {
                $this->keys = ['allowed_key'];
                $this->bootCount++;
            }

            private function bootHiddenFlag(): void
            {
                $this->bootCount++;
            }
        };

        $context->loadFromArray([
            'allowed_key' => [
                'description' => 'Allowed by boot configuration',
                'value' => 'ok',
            ],
        ]);

        $this->assertSame(2, $context->bootCount);
        $this->assertTrue($context->isKeyAllowed('allowed_key'));
        $this->assertFalse($context->isKeyAllowed('not_allowed_key'));
        $this->assertSame('ok', $context->getAllowedKeyValue());
    }
}

