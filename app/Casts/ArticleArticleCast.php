<?php

namespace App\Casts;

use App\Contracts\DOM\Article as DOMArticle;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ArticleArticleCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): DOMArticle
    {
        if ($value instanceof DOMArticle) {
            return $value;
        }

        if (is_array($value)) {
            return DOMArticle::fromArray($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return DOMArticle::fromArray($decoded);
            }
        }

        return DOMArticle::fromArray([]);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DOMArticle) {
            return $value->toJson();
        }

        if (is_array($value)) {
            return DOMArticle::fromArray($value)->toJson();
        }

        if (is_string($value)) {
            return $value;
        }

        return DOMArticle::fromArray([])->toJson();
    }
}
