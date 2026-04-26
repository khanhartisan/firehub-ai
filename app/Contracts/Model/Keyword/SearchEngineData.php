<?php

namespace App\Contracts\Model\Keyword;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;

/**
 * Driver-keyed payload storage for keyword search-engine metadata.
 *
 * Shape example:
 * [
 *   'google' => ['search_results' => [...], 'meta' => [...]],
 *   'serper' => ['search_results' => [...], 'meta' => [...]],
 * ]
 */
final class SearchEngineData implements Serializable
{
    use SerializableTrait;

    /**
     * @var array<string, SearchEngineDriverData>
     */
    protected array $drivers = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $instance = new static;

        foreach ($data as $driver => $driverData) {
            if (! is_string($driver)) {
                continue;
            }

            if ($driverData instanceof SearchEngineDriverData) {
                $instance->setDriverData($driver, $driverData);
                continue;
            }

            if (is_array($driverData)) {
                $instance->setDriverData($driver, SearchEngineDriverData::fromArray($driverData));
            }
        }

        return $instance;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        $out = [];
        foreach ($this->drivers as $driver => $data) {
            $out[$driver] = $data->toArray();
        }

        return $out;
    }

    /**
     * @return array<string, SearchEngineDriverData>
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    public function hasDriver(string $driver): bool
    {
        return array_key_exists($this->normalizeDriver($driver), $this->drivers);
    }

    /**
     * @return SearchEngineDriverData|null
     */
    public function getDriverData(string $driver, bool $autoCreate = false): ?SearchEngineDriverData
    {
        $key = $this->normalizeDriver($driver);

        return $this->drivers[$key] ??= ($autoCreate ? new SearchEngineDriverData() : null);
    }

    /**
     * @param  SearchEngineDriverData|array<string, mixed>  $data
     */
    public function setDriverData(string $driver, SearchEngineDriverData|array $data): static
    {
        $key = $this->normalizeDriver($driver);
        if ($key === '') {
            return $this;
        }

        $this->drivers[$key] = $data instanceof SearchEngineDriverData
            ? $data
            : SearchEngineDriverData::fromArray($data);

        return $this;
    }

    /**
     * @param  SearchEngineDriverData|array<string, mixed>  $data
     */
    public function mergeDriverData(string $driver, SearchEngineDriverData|array $data): static
    {
        $key = $this->normalizeDriver($driver);
        if ($key === '') {
            return $this;
        }

        $incoming = $data instanceof SearchEngineDriverData
            ? $data
            : SearchEngineDriverData::fromArray($data);

        $current = $this->drivers[$key] ?? new SearchEngineDriverData;
        if ($incoming->getSearchResults() !== null) {
            $current->setSearchResults($incoming->getSearchResults());
        }
        $current->mergeMeta($incoming->getMeta());

        $this->drivers[$key] = $current;

        return $this;
    }

    public function unsetDriver(string $driver): static
    {
        $key = $this->normalizeDriver($driver);
        unset($this->drivers[$key]);

        return $this;
    }

    protected function normalizeDriver(string $driver): string
    {
        return strtolower(trim($driver));
    }
}
