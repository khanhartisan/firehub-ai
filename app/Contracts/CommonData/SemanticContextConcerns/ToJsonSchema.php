<?php

namespace App\Contracts\CommonData\SemanticContextConcerns;

use App\Contracts\CommonData\AudienceContext;
use App\Contracts\CommonData\IdentifiableSemanticContext;
use App\Contracts\CommonData\SemanticContext;
use App\Enums\Country;
use BackedEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

trait ToJsonSchema
{
    /**
     * @return array<string, Type>
     */
    public function toJsonSchema(JsonSchema $schema): array
    {
        $template = $this->withEmptyFields(recursive: true, clone: true);
        $properties = [];

        foreach ($this->discoverFieldSetters() as $setter) {
            $key = $this->setterMethodToKey($setter->getName());
            if ($key === '' || ! $template->isKeyAllowed($key)) {
                continue;
            }

            $parameter = $setter->getParameters()[0];
            $fieldSchema = $this->jsonSchemaForParameter($schema, $parameter, $setter);

            $description = $template->getDescription($key);
            if (is_string($description) && $description !== '') {
                $fieldSchema = $fieldSchema->description($description);
            }

            $properties[$key] = $fieldSchema;
        }

        return $properties;
    }

    /**
     * @return ReflectionMethod[]
     */
    protected function discoverFieldSetters(): array
    {
        $reflection = new \ReflectionClass($this);
        $setters = array_filter(
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            function (ReflectionMethod $method): bool {
                if ($this instanceof IdentifiableSemanticContext && $method->getName() === 'setIdentifier') {
                    return false;
                }

                return str_starts_with($method->getName(), 'set')
                    && $method->getName() !== 'set'
                    && $method->getName() !== 'setWeight'
                    && $method->getNumberOfRequiredParameters() === 1;
            }
        );

        usort(
            $setters,
            static fn (ReflectionMethod $a, ReflectionMethod $b): int => strcmp($a->getName(), $b->getName())
        );

        return array_values($setters);
    }

    protected function setterMethodToKey(string $methodName): string
    {
        if (! str_starts_with($methodName, 'set') || strlen($methodName) <= 3) {
            return '';
        }

        $suffix = substr($methodName, 3);

        return ltrim(strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $suffix)), '_');
    }

    protected function jsonSchemaForParameter(
        JsonSchema $schema,
        ReflectionParameter $parameter,
        ReflectionMethod $setter,
    ): Type {
        $type = $parameter->getType();

        if ($type instanceof ReflectionUnionType) {
            return $this->jsonSchemaForUnionType($schema, $type, $parameter, $setter);
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->jsonSchemaForNamedType($schema, $type, $parameter, $setter);
        }

        $fieldSchema = $schema->string();

        return $parameter->allowsNull() ? $fieldSchema->nullable() : $fieldSchema;
    }

    protected function jsonSchemaForUnionType(
        JsonSchema $schema,
        ReflectionUnionType $type,
        ReflectionParameter $parameter,
        ReflectionMethod $setter,
    ): Type {
        $namedTypes = array_values(array_filter(
            $type->getTypes(),
            static fn (\ReflectionType $candidate): bool => $candidate instanceof ReflectionNamedType
                && $candidate->getName() !== 'null'
        ));

        if (count($namedTypes) === 1 && $namedTypes[0] instanceof ReflectionNamedType) {
            $fieldSchema = $this->jsonSchemaForNamedType($schema, $namedTypes[0], $parameter, $setter);
        } else {
            $fieldSchema = $schema->string();
        }

        if ($parameter->allowsNull() || $type->allowsNull()) {
            $fieldSchema = $fieldSchema->nullable();
        }

        return $fieldSchema;
    }

    protected function jsonSchemaForNamedType(
        JsonSchema $schema,
        ReflectionNamedType $type,
        ReflectionParameter $parameter,
        ReflectionMethod $setter,
    ): Type {
        $typeName = $type->getName();

        $fieldSchema = match ($typeName) {
            'bool', 'boolean' => $schema->boolean(),
            'int', 'integer' => $schema->integer(),
            'float', 'double' => $schema->number(),
            'string' => $schema->string(),
            'array' => $this->jsonSchemaForArrayParameter($schema, $parameter, $setter),
            default => $this->jsonSchemaForClassType($schema, $typeName),
        };

        if ($parameter->allowsNull() || $type->allowsNull()) {
            $fieldSchema = $fieldSchema->nullable();
        }

        return $fieldSchema;
    }

    protected function jsonSchemaForArrayParameter(
        JsonSchema $schema,
        ReflectionParameter $parameter,
        ReflectionMethod $setter,
    ): Type {
        return match ($setter->getName()) {
            'setAudienceContexts' => $schema->array()->items(
                $schema->object((new AudienceContext)->toJsonSchema($schema))->withoutAdditionalProperties()
            ),
            'setCountries' => $schema->array()->items($schema->string()->enum(Country::class)),
            'setMeta' => $schema->object(),
            default => $schema->array()->items($schema->string()),
        };
    }

    protected function jsonSchemaForClassType(JsonSchema $schema, string $typeName): Type
    {
        if (is_subclass_of($typeName, BackedEnum::class)) {
            return $schema->string()->enum($typeName);
        }

        if (class_exists($typeName) && is_subclass_of($typeName, SemanticContext::class)) {
            /** @var SemanticContext $nested */
            $nested = new $typeName;

            return $schema->object($nested->toJsonSchema($schema))->withoutAdditionalProperties();
        }

        return $schema->string();
    }
}
