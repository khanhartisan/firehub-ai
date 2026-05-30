<?php

namespace App\Contracts\Platforms\FlyCms;

use App\Contracts\ProvidesJsonSchema;
use App\Contracts\Serializable;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\ArrayType;
use Illuminate\JsonSchema\Types\BooleanType;
use Illuminate\JsonSchema\Types\IntegerType;
use Illuminate\JsonSchema\Types\NumberType;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\StringType;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

abstract class MutationData implements ProvidesJsonSchema, Serializable
{
    use \App\Concerns\Serializable;

    protected ?array $data = null;

    public function setData(?array $data): static
    {
        $this->validateData($data);
        $this->data = $data;

        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @throws ValidationException
     */
    public function validateData(?array $data): void
    {
        $rules = $this->validationRulesFromJsonSchema(
            $this->toJsonSchema(new JsonSchemaTypeFactory)
        );

        if ($rules === []) {
            return;
        }

        Validator::validate($data ?? [], $rules);
    }

    public function toArray(): array
    {
        return [
            'data' => $this->data,
        ];
    }

    /**
     * @throws ValidationException
     */
    public static function fromArray(array $data): static
    {
        $data = new static;
        $data->setData($data['data'] ?? null);

        return $data;
    }

    /**
     * @param  array<string, Type>  $properties
     * @return array<string, array<int, mixed>>
     */
    protected function validationRulesFromJsonSchema(array $properties): array
    {
        $rules = [];

        foreach ($properties as $key => $type) {
            $rules = array_merge($rules, $this->validationRulesForType($type, $key));
        }

        return $rules;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function validationRulesForType(Type $type, string $path): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = (fn () => get_object_vars($type))->call($type);
        $fieldRules = $this->presenceRules($attributes);

        return match ($type::class) {
            StringType::class => [$path => $this->appendStringTypeRules($attributes, $fieldRules)],
            IntegerType::class => [$path => $this->appendIntegerTypeRules($attributes, $fieldRules)],
            NumberType::class => [$path => $this->appendNumberTypeRules($attributes, $fieldRules)],
            BooleanType::class => [$path => $this->appendBooleanTypeRules($fieldRules)],
            ArrayType::class => $this->arrayTypeRules($attributes, $path, $fieldRules),
            ObjectType::class => $this->objectTypeRules($attributes, $path, $fieldRules),
            default => [$path => $fieldRules],
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    protected function presenceRules(array $attributes): array
    {
        if (($attributes['required'] ?? false) === true) {
            return ['required'];
        }

        if (($attributes['nullable'] ?? false) === true) {
            return ['nullable'];
        }

        return ['sometimes'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $fieldRules
     * @return list<string|Rule>
     */
    protected function appendStringTypeRules(array $attributes, array $fieldRules): array
    {
        $fieldRules[] = 'string';

        if (isset($attributes['enum']) && is_array($attributes['enum']) && $attributes['enum'] !== []) {
            $fieldRules[] = Rule::in($attributes['enum']);
        }

        if (isset($attributes['minLength'])) {
            $fieldRules[] = 'min:'.$attributes['minLength'];
        }

        if (isset($attributes['maxLength'])) {
            $fieldRules[] = 'max:'.$attributes['maxLength'];
        }

        if (isset($attributes['pattern'])) {
            $fieldRules[] = 'regex:'.$attributes['pattern'];
        }

        if (isset($attributes['format'])) {
            $formatRule = match ($attributes['format']) {
                'email' => 'email',
                'uuid' => 'uuid',
                'uri', 'url' => 'url',
                'date', 'date-time' => 'date',
                default => null,
            };

            if ($formatRule !== null) {
                $fieldRules[] = $formatRule;
            }
        }

        return $fieldRules;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $fieldRules
     * @return list<string>
     */
    protected function appendIntegerTypeRules(array $attributes, array $fieldRules): array
    {
        $fieldRules[] = 'integer';

        if (isset($attributes['minimum'])) {
            $fieldRules[] = 'min:'.$attributes['minimum'];
        }

        if (isset($attributes['maximum'])) {
            $fieldRules[] = 'max:'.$attributes['maximum'];
        }

        return $fieldRules;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $fieldRules
     * @return list<string>
     */
    protected function appendNumberTypeRules(array $attributes, array $fieldRules): array
    {
        $fieldRules[] = 'numeric';

        if (isset($attributes['minimum'])) {
            $fieldRules[] = 'min:'.$attributes['minimum'];
        }

        if (isset($attributes['maximum'])) {
            $fieldRules[] = 'max:'.$attributes['maximum'];
        }

        return $fieldRules;
    }

    /**
     * @param  list<string>  $fieldRules
     * @return list<string>
     */
    protected function appendBooleanTypeRules(array $fieldRules): array
    {
        $fieldRules[] = 'boolean';

        return $fieldRules;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $fieldRules
     * @return array<string, array<int, mixed>>
     */
    protected function arrayTypeRules(array $attributes, string $path, array $fieldRules): array
    {
        $fieldRules[] = 'array';

        if (isset($attributes['minItems'])) {
            $fieldRules[] = 'min:'.$attributes['minItems'];
        }

        if (isset($attributes['maxItems'])) {
            $fieldRules[] = 'max:'.$attributes['maxItems'];
        }

        if (($attributes['uniqueItems'] ?? false) === true) {
            $fieldRules[] = 'distinct';
        }

        $rules = [$path => $fieldRules];

        if (isset($attributes['items']) && $attributes['items'] instanceof Type) {
            $rules = array_merge(
                $rules,
                $this->validationRulesForType($attributes['items'], $path.'.*')
            );
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $fieldRules
     * @return array<string, array<int, mixed>>
     */
    protected function objectTypeRules(array $attributes, string $path, array $fieldRules): array
    {
        $fieldRules[] = 'array';

        $rules = [$path => $fieldRules];

        foreach ($attributes['properties'] ?? [] as $key => $property) {
            if ($property instanceof Type) {
                $rules = array_merge(
                    $rules,
                    $this->validationRulesForType($property, $path.'.'.$key)
                );
            }
        }

        return $rules;
    }
}
