<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Publication extends Model
{
    protected $casts = [
        'status' => PublicationStatus::class,
        'meta' => 'array',
        'published_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function publishable(): MorphTo
    {
        return $this->morphTo();
    }
}
