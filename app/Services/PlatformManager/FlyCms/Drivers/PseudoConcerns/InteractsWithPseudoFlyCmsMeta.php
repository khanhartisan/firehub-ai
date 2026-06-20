<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns;

use App\Contracts\PlatformManager\FlyCms\Filters\MetaFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\MetaMutationData\PutMetaData;
use App\Contracts\PlatformManager\FlyCms\Resources\MetaResource;
use Illuminate\Support\Str;
use InvalidArgumentException;

trait InteractsWithPseudoFlyCmsMeta
{
    /**
     * @return MetaResource[]
     */
    public function listMeta(string $metableType,
                             string $metableId,
                             int $page = 1,
                             int $limit = 100,
                             ?MetaFilter $metaFilter = null): array
    {
        $meta = array_values(array_filter(
            self::$meta,
            static fn (array $record): bool => ($record['metable_type'] ?? null) === $metableType
                && ($record['metable_id'] ?? null) === $metableId
        ));

        if ($metaFilter !== null) {
            $meta = $this->applyMetaFilter($meta, $metaFilter);
        }

        $offset = max(0, ($page - 1) * $limit);
        $meta = array_slice($meta, $offset, $limit);

        return array_map(
            static fn (array $record): MetaResource => new MetaResource($record),
            $meta
        );
    }

    /**
     * @return MetaResource[]
     */
    public function putMeta(PutMetaData $putMetaData): array
    {
        $data = $putMetaData->getData() ?? [];
        $metableType = $data['metable_type'] ?? null;
        $metableId = $data['metable_id'] ?? null;
        $meta = $data['meta'] ?? null;

        if (! is_string($metableType) || $metableType === '') {
            throw new InvalidArgumentException('Meta metable_type is required.');
        }

        if (! is_string($metableId) || $metableId === '') {
            throw new InvalidArgumentException('Meta metable_id is required.');
        }

        if (! is_array($meta)) {
            throw new InvalidArgumentException('Meta payload is required.');
        }

        if ($metableType !== 'website') {
            throw new InvalidArgumentException("Unsupported metable_type [{$metableType}].");
        }

        $website = self::$websites[$metableId] ?? null;

        if ($website === null) {
            throw new InvalidArgumentException("Website [{$metableId}] not found.");
        }

        $now = now()->toIso8601String();
        $websiteMeta = is_array($website['meta'] ?? null) ? $website['meta'] : [];
        $resources = [];

        foreach ($meta as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = $entry['key'] ?? null;
            $value = $entry['value'] ?? null;

            if (! is_string($key) || $key === '' || ! is_string($value)) {
                continue;
            }

            $existingMetaId = $this->findPseudoMetaId($metableType, $metableId, $key);

            if ($existingMetaId !== null) {
                self::$meta[$existingMetaId] = array_merge(self::$meta[$existingMetaId], [
                    'value' => $value,
                    'updated_at' => $now,
                ]);
                $resources[] = new MetaResource(self::$meta[$existingMetaId]);

                $websiteMeta[$key] = $value;

                continue;
            }

            $metaId = (string) Str::ulid();
            $record = [
                'id' => $metaId,
                'metable_type' => $metableType,
                'metable_id' => $metableId,
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            self::$meta[$metaId] = $record;
            $resources[] = new MetaResource($record);
            $websiteMeta[$key] = $value;
        }

        $website['meta'] = $websiteMeta;
        $website['updated_at'] = $now;
        self::$websites[$metableId] = $website;

        return $resources;
    }

    public function deleteMeta(string $metaId): bool
    {
        $record = self::$meta[$metaId] ?? null;

        if ($record === null) {
            return false;
        }

        unset(self::$meta[$metaId]);

        if (($record['metable_type'] ?? null) === 'website') {
            $websiteId = $record['metable_id'] ?? null;
            $key = $record['key'] ?? null;

            if (
                is_string($websiteId)
                && is_string($key)
                && isset(self::$websites[$websiteId]['meta'][$key])
            ) {
                unset(self::$websites[$websiteId]['meta'][$key]);
                self::$websites[$websiteId]['updated_at'] = now()->toIso8601String();
            }
        }

        return true;
    }

    protected function seedSampleMeta(): void
    {
        if (self::$meta !== []) {
            return;
        }

        $counter = 1;

        foreach (self::$websites as $website) {
            $websiteId = $website['id'] ?? null;
            $websiteMeta = is_array($website['meta'] ?? null) ? $website['meta'] : [];

            if (! is_string($websiteId)) {
                continue;
            }

            foreach ($websiteMeta as $key => $value) {
                $metaId = sprintf('01J0000000000000000000%04d', 1000 + $counter);
                $counter++;

                self::$meta[$metaId] = [
                    'id' => $metaId,
                    'metable_type' => 'website',
                    'metable_id' => $websiteId,
                    'key' => $key,
                    'value' => $value,
                    'created_at' => $website['created_at'] ?? now()->toIso8601String(),
                    'updated_at' => $website['updated_at'] ?? now()->toIso8601String(),
                ];
            }
        }
    }

    protected function findPseudoMetaId(string $metableType, string $metableId, string $key): ?string
    {
        foreach (self::$meta as $metaId => $record) {
            if (
                ($record['metable_type'] ?? null) === $metableType
                && ($record['metable_id'] ?? null) === $metableId
                && ($record['key'] ?? null) === $key
            ) {
                return $metaId;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $meta
     * @return array<int, array<string, mixed>>
     */
    protected function applyMetaFilter(array $meta, MetaFilter $metaFilter): array
    {
        $filterData = $metaFilter->getFilterData();
        $key = $filterData['key'] ?? null;

        if (! is_string($key) || $key === '') {
            return $meta;
        }

        return array_values(array_filter(
            $meta,
            static fn (array $record): bool => ($record['key'] ?? null) === $key
        ));
    }
}
