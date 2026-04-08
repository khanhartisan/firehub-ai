<?php

namespace App\Models;

use App\Contracts\Model\PageCountable as PageCountableContract;
use App\Models\Concerns\PageCountable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Vertical extends EmbeddableModel implements PageCountableContract, ShouldCascade
{
    use Cascades;
    use PageCountable;

    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'vector',
        'is_embeddable',
        'is_embedded',
    ];

    protected $casts = [
        'vector' => 'array',
        'is_embeddable' => 'boolean',
        'is_embedded' => 'boolean',
    ];

    public function isEmbeddable(): bool
    {
        return true;
    }

    public function getTextForEmbedding(): ?string
    {
        if (! $this->name and ! $this->description) {
            return null;
        }

        return $this->name.': '.$this->description;
    }

    public function isEmbedded(): bool
    {
        if (! $this->is_embedded) {
            return false;
        }

        if ($this->isDirty('name')
            or $this->isDirty('description')
            or ! $this->getTextForEmbedding()
        ) {
            return false;
        }

        return true;
    }

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->pages()),
            new CascadeDetails($this->children()),
            new CascadeDetails($this->hasMany(SourceVertical::class)),
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class)
            ->using(PageVertical::class)
            ->as('page_vertical');
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class)
            ->using(SourceVertical::class)
            ->as('source_vertical');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
