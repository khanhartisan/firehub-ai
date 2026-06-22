<?php

namespace App\Models;

use App\Contracts\PageParser\PageData;
use App\Enums\ScrapingStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Snapshot extends Model implements ShouldCascade
{
    use Cascades;

    protected $fillable = [
        'page_id',
        'scraping_status',
        'version',
        'file_path',
        'file_size',
        'file_mime_type',
        'file_extension',
        'content_length',
        'structured_data_count',
        'files_count',
        'links_count',
        'content_change_percentage',
        'fetch_duration_ms',
        'cost',
        'error_logs',
    ];

    protected $casts = [
        'scraping_status' => ScrapingStatus::class,
        'file_size' => 'integer',
        'content_length' => 'integer',
        'structured_data_count' => 'integer',
        'content_change_percentage' => 'float',
        'cost' => 'float',
        'fetch_duration_ms' => 'integer',
        'files_count' => 'integer',
        'links_count' => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function getFilePathForPageData(): string
    {
        return 'snapshots/'.$this->page_id.'/'.$this->id.'/page-data.json';
    }

    public function getPageData(): ?PageData
    {
        $pageDataFilePath = $this->getFilePathForPageData();
        if (!$pageDataJson = Storage::get($pageDataFilePath)) {
            return null;
        }

        return PageData::fromJson($pageDataJson);
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

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails(
                $this
                    ->hasMany(Fileable::class, 'fileable_id')
                    ->where('fileable_type', $this->getMorphClass())
            ),
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return false;
    }
}
