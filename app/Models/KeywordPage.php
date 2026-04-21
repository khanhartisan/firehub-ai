<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordPage extends Pivot
{
    protected $casts = [
        'position' => 'integer',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
