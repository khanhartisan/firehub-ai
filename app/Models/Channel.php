<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Channel extends Model implements ShouldCascade
{
    use Cascades;

    protected $casts = [
        'config' => 'array'
    ];

    public function getCascadeDetails(): CascadeDetails|array
    {
        return new CascadeDetails($this->publications());
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }
}
