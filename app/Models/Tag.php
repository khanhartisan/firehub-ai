<?php

namespace App\Models;

use App\Contracts\Model\Embeddable as EmbeddableContract;
use App\Contracts\Model\EntityCountable as EntityCountableContract;
use App\Models\Concerns\EntityCountable;
use App\Models\Concerns\Embeddable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Tag extends Model implements EmbeddableContract, EntityCountableContract, ShouldCascade
{
    use Cascades;
    use Embeddable;
    use EntityCountable;

    protected $fillable = [
        'name',
        'vector',
        'is_embedded',
    ];

    protected $casts = [
        'is_embedded' => 'boolean',
    ];

    public function isEmbeddable(): bool
    {
        return true;
    }

    public function getTextForEmbedding(): ?string
    {
        // TODO: Implement getTextForEmbedding() method.
        return null;
    }

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
