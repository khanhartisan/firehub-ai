<?php

namespace App\Casts;

use App\Contracts\PlatformManager\Config;
use App\Models\Channel;
use App\Models\Platform;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ChannelConfigCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Config
    {
        if (!$model instanceof Channel) {
            throw new \InvalidArgumentException('ChannelConfigCast can only be used on the Channel model.');
        }

        if ($value instanceof Config) {
            return $value;
        }

        return $this->hydrate($model, $this->decodeValue($value), $attributes);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (!$model instanceof Channel) {
            throw new \InvalidArgumentException('ChannelConfigCast can only be used on the Channel model.');
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

    private function hydrate(Channel $channel, array $data, array $attributes): ?Config
    {
        $platform = $this->resolvePlatform($channel, $attributes);

        if ($platform === null) {
            return null;
        }

        $channelConfig = $platform->getPlatformManager()->makeChannelConfig();

        if ($channelConfig === null) {
            return null;
        }

        return $channelConfig->setConfig($data);
    }

    private function resolvePlatform(Channel $channel, array $attributes): ?Platform
    {
        if ($channel->relationLoaded('platform')) {
            return $channel->platform;
        }

        if ($channel->platform_id !== null) {
            return $channel->platform;
        }

        $platformId = $attributes['platform_id'] ?? null;

        if ($platformId === null) {
            return null;
        }

        return Platform::query()->find($platformId);
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
