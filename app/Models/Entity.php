<?php

namespace App\Models;

use App\Contracts\Model\EntityCountable;
use App\Enums\ContentType;
use App\Enums\EntityType;
use App\Enums\PageType;
use App\Enums\ScrapingStatus;
use App\Enums\Temporal;
use App\Database\Eloquent\Relations\EntityCountBelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Entity extends Model implements ShouldCascade
{
    use Cascades;

    protected $fillable = [
        'source_id',
        'url',
        'description',
        'type',
        'scraping_status',
    ];

    protected $casts = [
        'canonical_number' => 'integer',
        'attempts' => 'integer',
        'type' => EntityType::class,
        'scraping_status' => ScrapingStatus::class,
        'page_type' => PageType::class,
        'content_type' => ContentType::class,
        'temporal' => Temporal::class,
        'source_published_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'fetched_at' => 'datetime',
        'next_scrape_at' => 'datetime',
        'policy_result' => 'array',
    ];

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->snapshots()),
            new CascadeDetails($this->hasMany(EntityVertical::class)),
            new CascadeDetails($this->hasMany(EntityTag::class))
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
        $relation = new EntityCountBelongsToMany(
            $query,
            $parent,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName
        );

        if ($query->getModel() instanceof EntityCountable) {
            $relation->syncEntityCounts();
        }

        return $relation;
    }

    public function canonicalEntity(): BelongsTo
    {
        return $this->belongsTo(static::class, 'canonical_entity_id');
    }

    public function aliasEntities(): HasMany
    {
        return $this->hasMany(static::class, 'canonical_entity_id');
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

    public function relatedEntities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class,
            'entity_relations',
            'source_entity_id',
            'related_entity_id'
        );
    }

    public function relatedByEntities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class,
            'entity_relations',
            'related_entity_id',
            'source_entity_id'
        );
    }

    public function verticals(): BelongsToMany
    {
        return $this->belongsToMany(Vertical::class)
            ->using(EntityVertical::class)
            ->as('entity_vertical');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->using(EntityTag::class)
            ->as('entity_tag');
    }

    /**
     * @return EntityCountable[]
     */
    public function getEntityCountableResources(): array
    {
        return [
            $this->source,
            ...$this->verticals,
            ...$this->tags,
        ];
    }
}
