<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PublishingScheduleRelation extends Model
{
    public function publishingSchedule(): BelongsTo
    {
        return $this->belongsTo(PublishingSchedule::class);
    }

    public function relation(): MorphTo
    {
        return $this->morphTo();
    }
}
