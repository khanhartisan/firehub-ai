<?php

namespace App\Models;

use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;

class Article extends EmbeddableModel
{
    protected $casts = [
        'stage' => ArticleStage::class,
        'stage_status' => ArticleStageStatus::class,
        'vector' => 'array',
        'is_embeddable' => 'boolean',
        'is_embedded' => 'boolean',
    ];

    public function isEmbeddable(): bool
    {
        return $this->stage === ArticleStage::FINAL
            and $this->stage_status === ArticleStageStatus::APPROVED
            and ($this->title
                or $this->excerpt
                or $this->body_markdown
            );
    }

    public function isEmbedded(): bool
    {
        if (!$this->is_embedded) {
            return false;
        }

        if ($this->isDirty('title')
            or $this->isDirty('excerpt')
            or $this->isDirty('body_markdown')
        ) {
            return false;
        }

        return true;
    }

    public function getTextForEmbedding(): ?string
    {
        return '# '.$this->title."\n\n".$this->excerpt."\n\n".$this->body_markdown;
    }
}
