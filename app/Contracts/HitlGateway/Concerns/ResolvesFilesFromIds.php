<?php

namespace App\Contracts\HitlGateway\Concerns;

use App\Models\File;

trait ResolvesFilesFromIds
{
    /**
     * @param  array<int, mixed>  $values
     * @return File[]
     */
    protected static function filesFromMixedList(array $values): array
    {
        $ids = [];

        foreach ($values as $value) {
            if ($value instanceof File) {
                if ($value->getKey() !== null && $value->getKey() !== '') {
                    $ids[] = (string) $value->getKey();
                }

                continue;
            }

            if (! is_string($value) && ! is_int($value)) {
                continue;
            }

            $id = (string) $value;
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        $byId = File::findMany($ids)->keyBy(
            static fn (File $file): string => (string) $file->getKey()
        );

        $files = [];
        foreach ($ids as $id) {
            $file = $byId->get($id);
            if ($file instanceof File) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
