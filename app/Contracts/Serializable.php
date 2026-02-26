<?php

namespace App\Contracts;

/**
 * Objects that can be serialized to/from array and JSON.
 *
 * Used by DTOs (e.g. ClassificationResult, PageData, PolicyResult) for storage,
 * logging, and API payloads. toArray()/fromArray() and toJson()/fromJson() must be symmetric.
 */
interface Serializable
{
    /**
     * Convert the object to an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Create an instance from an array representation.
     *
     * @param  array<string, mixed>  $data
     * @return static
     */
    public static function fromArray(array $data): static;

    /**
     * Convert the object to a JSON string representation.
     *
     * @param  int  $flags
     * @return string
     */
    public function toJson(int $flags = 0): string;

    /**
     * Create an instance from a JSON string representation.
     *
     * @param  string  $json
     * @param  int  $flags
     * @return static
     */
    public static function fromJson(string $json, int $flags = 0): static;
}
