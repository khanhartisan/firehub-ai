<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntentKeyword extends Pivot
{
    public function intent(): BelongsTo
    {
        return $this->belongsTo(Intent::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
