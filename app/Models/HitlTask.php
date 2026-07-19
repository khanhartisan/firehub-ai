<?php

namespace App\Models;

use App\Contracts\HitlGateway\TaskStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HitlTask extends Model
{
    protected $casts = [
        'status' => TaskStatus::class,
        'data' => 'array',
        'conclusion' => 'array',
    ];

    public function hitlPlatform(): BelongsTo
    {
        return $this->belongsTo(HitlPlatform::class);
    }
}
