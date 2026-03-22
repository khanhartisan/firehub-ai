<?php

namespace App\Models;

use App\Contracts\Model\EntityCountable as EntityCountableContract;
use App\Models\Concerns\EntityCountable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Source extends EmbeddableModel implements EntityCountableContract, ShouldCascade
{
    use Cascades;
    use EntityCountable;
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
            new CascadeDetails($this->entities()),
            new CascadeDetails($this->hasMany(SourceVertical::class)),
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }

    public function verticals(): BelongsToMany
    {
        return $this->belongsToMany(Vertical::class)
            ->using(SourceVertical::class)
            ->as('source_vertical');
    }
}
