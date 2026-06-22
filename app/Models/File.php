<?php

namespace App\Models;

use App\Enums\ScrapingStage;
use App\Enums\ScrapingStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class File extends EmbeddableModel implements ShouldCascade
{
    use Cascades;

    public function getCascadeDetails(): CascadeDetails|array
    {
        return new CascadeDetails($this->fileables());
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return false;
    }

    protected $casts = [
        'scraping_status' => ScrapingStatus::class,
        'scraping_stage' => ScrapingStage::class,
        'size' => 'integer',
        'fetch_duration_ms' => 'integer',
        'cost' => 'float',
        'scraped_at' => 'datetime',
        'attempts' => 'integer',
        'vector' => 'array',
        'is_embeddable' => 'boolean',
        'is_embedded' => 'boolean',
        'fileables_count' => 'integer',
    ];

    public function fileables(): HasMany
    {
        return $this->hasMany(Fileable::class);
    }

    public function isEmbeddable(): bool
    {
        return $this->scraping_status === ScrapingStatus::SUCCESS
            and $this->getTextForEmbedding()
            and in_array($this->extension, ['jpg', 'jpeg', 'png', 'webp']);
    }

    public function isEmbedded(): bool
    {
        if (! $this->is_embedded) {
            return false;
        }

        if ($this->isDirty('scraping_status')
            or $this->isDirty('description')
            or ! $this->getTextForEmbedding()
        ) {
            return false;
        }

        return true;
    }

    public function getTextForEmbedding(): ?string
    {
        return $this->description;
    }
}
