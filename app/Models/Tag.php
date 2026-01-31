<?php

namespace App\Models;

use App\Contracts\Model\EntityCountable as EntityCountableContract;
use App\Models\Concerns\EntityCountable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model implements EntityCountableContract
{
    use EntityCountable;

    protected $fillable = [
        'name',
    ];

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class)
            ->using(EntityTag::class)
            ->as('entity_tag');
    }
}
