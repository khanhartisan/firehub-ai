<?php

namespace App\Models;

use App\Contracts\Model\EntityCountable as EntityCountableContract;
use App\Models\Concerns\EntityCountable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Source extends Model implements EntityCountableContract, ShouldCascade
{
    use EntityCountable;
    use Cascades;

    protected $fillable = [
        'base_url',
        'authority_score',
        'priority',
    ];

    protected $casts = [
        'authority_score' => 'integer',
    ];

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
