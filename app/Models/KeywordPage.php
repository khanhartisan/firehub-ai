<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordPage extends Pivot
{
    protected $casts = [
        'relevance' => 'float',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function Page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
