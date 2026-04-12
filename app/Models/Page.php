<?php

namespace App\Models;

use App\Contracts\Model\PageCountable;
use App\Database\Eloquent\Relations\PageCountBelongsToMany;
use App\Enums\ContentType;
use App\Enums\PageType;
use App\Enums\ScrapableType;
use App\Enums\ScrapingStage;
use App\Enums\ScrapingStatus;
use App\Enums\Temporal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Page extends EmbeddableModel implements ShouldCascade
{
    use Cascades;

    protected $fillable = [
        'source_id',
        'canonical_page_id',
        'canonical_number',
        'url',
        'title',
        'description',
        'type',
        'ignore_scraping_budget',
        'scraping_status',
        'scraping_stage',
        'page_type',
        'content_type',
        'temporal',
        'version_index',
        'source_published_at',
        'source_updated_at',
        'scraped_at',
        'attempts',
        'next_scrape_at',
        'vector',
        'is_embeddable',
        'is_embedded',
    ];

    protected $casts = [
        'canonical_number' => 'integer',
        'attempts' => 'integer',
        'type' => ScrapableType::class,
        'ignore_scraping_budget' => 'boolean',
        'scraping_status' => ScrapingStatus::class,
        'scraping_stage' => ScrapingStage::class,
        'page_type' => PageType::class,
        'content_type' => ContentType::class,
        'temporal' => Temporal::class,
        'source_published_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'scraped_at' => 'datetime',
        'next_scrape_at' => 'datetime',
        'policy_result' => 'array',
        'vector' => 'array',
        'is_embeddable' => 'boolean',
        'is_embedded' => 'boolean',
        'version_index' => 'integer',
        'intents_count' => 'integer',
        'intent_resolved_at' => 'datetime',
    ];

    public function isEmbeddable(): bool
    {
        return $this->type === ScrapableType::TEXT
            and $this->page_type === PageType::DETAIL
            and $this->scraping_status === ScrapingStatus::SUCCESS;
    }

    public function isEmbedded(): bool
    {
        if (! $this->is_embedded) {
            return false;
        }

        if ($this->isDirty('page_type')
            or $this->isDirty('content_type')
            or $this->isDirty('description')
            or ! $this->getTextForEmbedding()
        ) {
            return false;
        }

        return true;
    }

    public function getTextForEmbedding(): ?string
    {
        if (! $this->isEmbeddable()) {
            return null;
        }

        $text = '';
        if ($this->page_type) {
            $text .= 'Page type: '.$this->page_type->name.' ('.PageType::describe($this->page_type).')'."\n";
        }

        if ($this->content_type) {
            $text .= 'Content type: '.$this->content_type->name.' ('.ContentType::describe($this->content_type).')'."\n";
        }

        if ($this->title) {
            $text .= 'Title: '.$this->title;
        }

        $text .= 'Description: '.$this->description;

        return $text;
    }

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->snapshots()),
            new CascadeDetails($this->hasMany(PageVertical::class)),
            new CascadeDetails($this->hasMany(PageTag::class)),
            new CascadeDetails($this->intentPages()),
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    protected function newBelongsToMany(
        Builder $query,
        EloquentModel $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
    ): BelongsToMany {
        $relation = new PageCountBelongsToMany(
            $query,
            $parent,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName
        );

        if ($query->getModel() instanceof PageCountable) {
            $relation->syncPageCounts();
        }

        return $relation;
    }

    public function canonicalPage(): BelongsTo
    {
        return $this->belongsTo(static::class, 'canonical_page_id');
    }

    public function aliasPages(): HasMany
    {
        return $this->hasMany(static::class, 'canonical_page_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class);
    }

    public function currentSnapshot(): HasOne
    {
        return $this->hasOne(Snapshot::class)->orderByDesc('version');
    }

    public function relatedPages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class,
            'page_relations',
            'source_page_id',
            'related_page_id'
        );
    }

    public function relatedByPages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class,
            'page_relations',
            'related_page_id',
            'source_page_id'
        );
    }

    public function verticals(): BelongsToMany
    {
        return $this->belongsToMany(Vertical::class)
            ->using(PageVertical::class)
            ->as('page_vertical');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->using(PageTag::class)
            ->as('page_tag');
    }

    public function intentPages(): HasMany
    {
        return $this->hasMany(IntentPage::class);
    }

    public function intents(): BelongsToMany
    {
        return $this->belongsToMany(Intent::class)
            ->using(IntentPage::class)
            ->as('intent_page')
            ->withPivot([
                'relevance'
            ]);
    }

    /**
     * @return PageCountable[]
     */
    public function getPageCountableResources(): array
    {
        return [
            $this->source,
            ...$this->verticals,
            ...$this->tags,
        ];
    }
}
