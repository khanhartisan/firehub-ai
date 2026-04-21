<?php

namespace App\Casts;

use App\Contracts\Model\Client\GeneralContext;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ClientGeneralContextCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): GeneralContext
    {
        if ($value instanceof GeneralContext) {
            return $value;
        }

        if (is_array($value)) {
            return GeneralContext::fromArray($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return GeneralContext::fromArray($decoded);
            }
        }

        return GeneralContext::fromArray([]);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof GeneralContext) {
            return $value->toJson();
        }

        if (is_array($value)) {
            return GeneralContext::fromArray($value)->toJson();
        }

        if (is_string($value)) {
            return $value;
        }

        return GeneralContext::fromArray([])->toJson();
    }
}

