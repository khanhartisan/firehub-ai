<?php

namespace App\Casts;

use App\Contracts\PlatformManager\Config;
use App\Models\Platform;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PlatformConfigCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Config
    {
        if (!$model instanceof Platform) {
            throw new \InvalidArgumentException('PlatformConfigCast can only be used on the Platform model.');
        }

        if ($value instanceof Config) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return $this->hydrate($model, $this->decodeValue($value), $attributes);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (!$model instanceof Platform) {
            throw new \InvalidArgumentException('PlatformConfigCast can only be used on the Platform model.');
        }

        if ($value === null) {
            return null;
        }

        if ($value instanceof Config) {
            return $value->toJson();
        }

        if (is_array($value)) {
            return $this->hydrate($model, $value, $attributes)?->toJson();
        }

        if (is_string($value)) {
            return $value;
        }

        return $this->hydrate($model, [], $attributes)?->toJson();
    }

    /**
     * @throws \Exception
     */
    private function hydrate(Platform $platform, array $data, array $attributes): ?Config
    {
        $platformConfig = $platform->getPlatformManager()->makeConfig();

        if ($platformConfig === null) {
            return null;
        }

        return $platformConfig->setConfig($data);
    }

    private function decodeValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
