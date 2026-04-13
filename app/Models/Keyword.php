<?php

namespace App\Models;

use App\Utils\Str;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Keyword extends Model implements ShouldCascade
{
    use Cascades;

    protected $casts = [
        'global_volume' => 'integer',
        'volume_by_country' => 'integer',
        'difficulty' => 'float',
        'intents_count' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public static function makeHash(string $keyword): string
    {
        return sha1(Str::sanitizeKeyword($keyword));
    }

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->intentKeywords())
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    public function intentKeywords(): HasMany
    {
        return $this->hasMany(IntentKeyword::class);
    }

    public function intents(): BelongsToMany
    {
        return $this->belongsToMany(Intent::class)
            ->using(IntentKeyword::class)
            ->as('intent_keyword')
            ->withPivot([
                'relevance'
            ]);
    }
}
