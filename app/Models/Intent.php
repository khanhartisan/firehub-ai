<?php

namespace App\Models;

use App\Enums\IntentType;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Intent extends Model implements ShouldCascade
{
    use Cascades;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'types' => AsEnumCollection::of(IntentType::class),
            'keywords_count' => 'integer',
            'pages_count' => 'integer',
            'articles_count' => 'integer',
            'vector' => 'array',
            'is_embeddable' => 'boolean',
            'is_embedded' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->intentKeywords()),
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

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class)
            ->using(IntentKeyword::class)
            ->as('intent_keyword')
            ->withPivot([
                'relevance',
            ]);
    }
}
