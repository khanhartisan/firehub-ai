<?php

namespace App\Casts;

use App\Contracts\Model\HitlPlatform\Context;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class HitlPlatformContextCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): Context
    {
        if ($value instanceof Context) {
            return $value;
        }

        if (is_array($value)) {
            return Context::fromArray($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return Context::fromArray($decoded);
            }
        }

        return Context::fromArray([]);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Context) {
            return $value->toJson();
        }

        if (is_array($value)) {
            return Context::fromArray($value)->toJson();
        }

        if (is_string($value)) {
            return $value;
        }

        return Context::fromArray([])->toJson();
    }
}
