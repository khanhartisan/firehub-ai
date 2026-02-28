<?php

namespace App\Models;

use App\Contracts\Model\EntityCountable as EntityCountableContract;
use App\Models\Concerns\EntityCountable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Vertical extends Model implements EntityCountableContract, ShouldCascade
{
    use EntityCountable;
    use Cascades;

    protected $fillable = [
        'name',
        'description',
        'parent_id',
    ];

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->entities()),
            new CascadeDetails($this->children()),
            new CascadeDetails($this->hasMany(SourceVertical::class))
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class)
            ->using(EntityVertical::class)
            ->as('entity_vertical');
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
