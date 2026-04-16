<?php

namespace App\Casts;

use App\Contracts\Model\Article\StageData;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ArticleStageDataCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): StageData
    {
        if ($value instanceof StageData) {
            return $value;
        }

        if (is_array($value)) {
            return StageData::fromArray($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return StageData::fromArray($decoded);
            }
        }

        return StageData::fromArray([]);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof StageData) {
            return $value->toJson();
        }

        if (is_array($value)) {
            return StageData::fromArray($value)->toJson();
        }

        if (is_string($value)) {
            return $value;
        }

        return StageData::fromArray([])->toJson();
    }
}
