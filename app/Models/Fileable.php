<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Fileable extends Model
{
    protected $fillable = [
        'fileable_type', 'fileable_id', 'file_id'
    ];

    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
