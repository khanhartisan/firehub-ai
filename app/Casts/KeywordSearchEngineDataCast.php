<?php

namespace App\Casts;

use App\Contracts\Model\Keyword\SearchEngineData;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class KeywordSearchEngineDataCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): SearchEngineData
    {
        if ($value instanceof SearchEngineData) {
            return $value;
        }

        if (is_array($value)) {
            return SearchEngineData::fromArray($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return SearchEngineData::fromArray($decoded);
            }
        }

        return SearchEngineData::fromArray([]);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof SearchEngineData) {
            return $value->toJson();
        }

        if (is_array($value)) {
            return SearchEngineData::fromArray($value)->toJson();
        }

        if (is_string($value)) {
            return $value;
        }

        return SearchEngineData::fromArray([])->toJson();
    }
}
