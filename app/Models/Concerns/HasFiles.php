<?php

namespace App\Models\Concerns;

use App\Models\File;
use App\Models\Fileable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasFiles
{
    public function files(): BelongsToMany
    {
        return $this->belongsToMany(
            File::class,
            'fileables',
            'fileable_id',
            'file_id'
        )->where('fileables.fileable_type', $this->getMorphClass());
    }

    public function fileables(): HasMany
    {
        return $this
            ->hasMany(Fileable::class, 'fileable_id')
            ->where('fileable_type', $this->getMorphClass());
    }
}