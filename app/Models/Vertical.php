<?php

namespace App\Models;

use App\Contracts\Model\EntityCountable as EntityCountableContract;
use App\Models\Concerns\EntityCountable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vertical extends Model implements EntityCountableContract
{
    use EntityCountable;

    protected $fillable = [
        'name',
        'description',
        'parent_id',
    ];

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
