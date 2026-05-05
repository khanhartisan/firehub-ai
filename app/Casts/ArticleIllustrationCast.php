<?php

namespace App\Casts;

use App\Contracts\Model\Article\IllustrationData;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ArticleIllustrationCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): IllustrationData
    {
        if ($value instanceof IllustrationData) {
            return $value;
        }

        if (is_array($value)) {
            return IllustrationData::fromArray($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return IllustrationData::fromArray($decoded);
            }
        }

        return IllustrationData::fromArray([]);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof IllustrationData) {
            return $value->toJson();
        }

        if (is_array($value)) {
            return IllustrationData::fromArray($value)->toJson();
        }

        if (is_string($value)) {
            return $value;
        }

        return IllustrationData::fromArray([])->toJson();
    }
}
