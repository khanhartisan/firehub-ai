<?php

namespace App\Models;

use App\Contracts\Model\PageCountable as PageCountableContract;
use App\Models\Concerns\PageCountable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Source extends EmbeddableModel implements PageCountableContract, ShouldCascade
{
    use Cascades;
    use PageCountable;
    use HasFactory;

    protected $fillable = [
        'base_url',
        'authority_score',
        'priority',
        'vector',
        'is_embedded',
    ];

    protected $casts = [
        'authority_score' => 'integer',
        'daily_budget' => 'integer',
        'weekly_budget' => 'integer',
        'monthly_budget' => 'integer',
        'vector' => 'array',
        'is_embeddable' => 'boolean',
        'is_embedded' => 'boolean',
    ];

    public function isEmbeddable(): bool
    {
        return !!$this->description;
    }

    public function isEmbedded(): bool
    {
        if (!$this->is_embedded) {
            return false;
        }

        if ($this->isDirty('description')
            or !$this->getTextForEmbedding()
        ) {
            return false;
        }

        return true;
    }

    public function getTextForEmbedding(): ?string
    {
        return $this->description ?: null;
    }

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->pages()),
            new CascadeDetails($this->hasMany(SourceVertical::class)),
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function verticals(): BelongsToMany
    {
        return $this->belongsToMany(Vertical::class)
            ->using(SourceVertical::class)
            ->as('source_vertical');
    }
}
