<?php

namespace App\Models;

use App\Enums\ScrapingStatus;
use Illuminate\Database\Eloquent\Model;

class File extends EmbeddableModel
{
    protected $casts = [
        'scraping_status' => ScrapingStatus::class,
        'size' => 'integer',
        'fetch_duration_ms' => 'integer',
        'cost' => 'float',
        'scraped_at' => 'datetime',
        'vector' => 'array',
        'is_embeddable' => 'boolean',
        'is_embedded' => 'boolean',
    ];

    public function isEmbeddable(): bool
    {
        return $this->scraping_status === ScrapingStatus::SUCCESS
            and $this->getTextForEmbedding()
            and in_array($this->extension, ['jpg', 'jpeg', 'png', 'webp']);
    }

    public function isEmbedded(): bool
    {
        if (!$this->is_embedded) {
            return false;
        }

        if ($this->isDirty('scraping_status')
            or $this->isDirty('description')
            or !$this->getTextForEmbedding()
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
