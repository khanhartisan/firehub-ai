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
            $value->setIdentifier($this->getContextIdentifier($model));
            return $value;
        }

        if (is_array($value)) {
            $context = AuthorContext::fromArray($value);
            $context->setIdentifier($this->getContextIdentifier($model));
            return $context;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $context = AuthorContext::fromArray($decoded);
                $context->setIdentifier($this->getContextIdentifier($model));
                return $context;
            }
        }

        return AuthorContext::fromArray([])->setIdentifier($this->getContextIdentifier($model));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof AuthorContext) {
            $value->setIdentifier($this->getContextIdentifier($model));
            return $value->toJson();
        }

        if (is_array($value)) {
            $context = AuthorContext::fromArray($value);
            $context->setIdentifier($this->getContextIdentifier($model));
            return $context->toJson();
        }

        if (is_string($value)) {
            return $value;
        }

        return AuthorContext::fromArray([])
            ->setIdentifier($this->getContextIdentifier($model))
            ->toJson();
    }

    protected function getContextIdentifier(Model $model): string
    {
        return 'author-ctx-' . $model->getKey();
    }
}
