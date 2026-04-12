<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntentPage extends Pivot
{
    protected $casts = [
        'relevance' => 'float',
    ];

    public function intent(): BelongsTo
    {
        return $this->belongsTo(Intent::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
