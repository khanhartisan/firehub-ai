<?php

namespace App\Models;

use App\Enums\ScrapingStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'files_count' => 'integer',
        'links_count' => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function files(): BelongsToMany
    {
        return $this->belongsToMany(
            File::class,
            'fileables',
            'fileable_id',
            'file_id'
        )->where('fileables.fileable_type', $this->getMorphClass());
    }
}
