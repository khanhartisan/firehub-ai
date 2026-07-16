<?php

namespace App\Casts;

use App\Contracts\CommonData\SemanticContext;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class SemanticContextCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): SemanticContext
    {
        if ($value instanceof SemanticContext) {
            return $value;
        }

        if (is_array($value)) {
            return SemanticContext::fromArray($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return SemanticContext::fromArray($decoded);
            }
        }

        return SemanticContext::fromArray([]);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof SemanticContext) {
            return $value->toJson();
        }

        if (is_array($value)) {
            return SemanticContext::fromArray($value)->toJson();
        }

        if (is_string($value)) {
            return $value;
        }

        return SemanticContext::fromArray([])->toJson();
    }
}
