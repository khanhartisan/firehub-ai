<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageRelation extends Model
{
    public function sourcePage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'source_page_id');
    }

    public function relatedPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'related_page_id');
    }
}
