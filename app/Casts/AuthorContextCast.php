<?php

namespace App\Casts;

use App\Contracts\Model\Author\AuthorContext;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class AuthorContextCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): AuthorContext
    {
        if ($value instanceof AuthorContext) {
            return $value;
        }

        if (is_array($value)) {
            return AuthorContext::fromArray($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return AuthorContext::fromArray($decoded);
            }
        }

        return AuthorContext::fromArray([]);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof AuthorContext) {
            return $value->toJson();
        }

        if (is_array($value)) {
            return AuthorContext::fromArray($value)->toJson();
        }

        if (is_string($value)) {
            return $value;
        }

        return AuthorContext::fromArray([])->toJson();
    }
}
