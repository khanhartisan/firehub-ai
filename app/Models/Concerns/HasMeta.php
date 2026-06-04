<?php

namespace App\Models\Concerns;

use App\Models\Meta;
use App\Utils\Str;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMeta
{
    public function meta(): MorphMany
    {
        return $this->morphMany(Meta::class, 'metable');
    }

    public function putMeta(string $key, mixed $value): bool
    {
        return !!$this->meta()->updateOrInsert([
            'metable_type' => $this->getMorphClass(),
            'metable_id' => $this->getKey(),
            'key' => $key,
        ], [
            'id' => strtolower(Str::ulid()->toString()),
            'value' => $value,
        ]);
    }

    public function getMeta(string $key): ?Meta
    {
        return $this->meta()->where('key', $key)->first();
    }

    public function getMetaValue(string $key): ?string
    {
        return $this->getMeta($key)?->value;
    }
}