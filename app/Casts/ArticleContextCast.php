<?php

namespace App\Casts;

use App\Contracts\Model\Article\Context;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ArticleContextCast implements CastsAttributes
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

            return (new Context)->setMeta(['raw_text' => $value]);
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
            return (new Context)->setMeta(['raw_text' => $value])->toJson();
        }

        return Context::fromArray([])->toJson();
    }
}

