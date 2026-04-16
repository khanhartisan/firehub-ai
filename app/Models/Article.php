<?php

namespace App\Models;

use App\Casts\ArticleStageDataCast;
use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use App\Enums\Language;
use App\Enums\Temporal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Article extends EmbeddableModel implements ShouldCascade
{
    use Cascades;

    protected $casts = [
        'status' => ArticleStatus::class,
        'language' => Language::class,
        'temporal' => Temporal::class,
        'stage' => ArticleStage::class,
        'stage_status' => ArticleStageStatus::class,
        'stage_data' => ArticleStageDataCast::class,
        'vector' => 'array',
        'is_embeddable' => 'boolean',
        'is_embedded' => 'boolean',
        'attempts' => 'integer',
        'intents_count' => 'integer',
        'intent_resolved_at' => 'datetime',
    ];

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->articleIntents())
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function articleIntents(): HasMany
    {
        return $this->hasMany(ArticleIntent::class);
    }

    public function intents(): BelongsToMany
    {
        return $this->belongsToMany(Intent::class)
            ->using(ArticleIntent::class)
            ->as('article_intent')
            ->withPivot(['relevance']);
    }

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
