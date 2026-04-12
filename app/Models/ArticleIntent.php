<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleIntent extends Pivot
{
    protected $casts = [
        'relevance' => 'float',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(Intent::class);
    }
}
