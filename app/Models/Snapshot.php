<?php

namespace App\Models;

use App\Enums\ScrapingStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Snapshot extends Model
{
    protected $fillable = [
        'page_id',
        'scraping_status',
        'version',
        'file_path',
        'file_size',
        'file_mime_type',
        'file_extension',
        'error_logs',
    ];

    protected $casts = [
        'scraping_status' => ScrapingStatus::class,
        'content_change_percentage' => 'float',
        'cost' => 'float',
        'media_count' => 'integer',
        'links_count' => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
