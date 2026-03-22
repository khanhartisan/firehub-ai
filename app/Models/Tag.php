<?php

namespace App\Models;

use App\Contracts\Model\EntityCountable as EntityCountableContract;
use App\Models\Concerns\EntityCountable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Tag extends Model implements EntityCountableContract, ShouldCascade
{
    use Cascades;
    use EntityCountable;

    protected $fillable = [
        'name',
    ];

    public function getCascadeDetails(): CascadeDetails|array
    {
        return new CascadeDetails($this->hasMany(EntityTag::class));
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class)
            ->using(EntityTag::class)
            ->as('entity_tag');
    }
}
