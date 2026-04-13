<?php

namespace App\Models;

use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Intent extends EmbeddableModel implements ShouldCascade
{
    use Cascades;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'language' => Language::class,
            'temporal' => Temporal::class,
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
            new CascadeDetails($this->articleIntents()),
            new CascadeDetails($this->intentPages()),
            new CascadeDetails($this->intentKeywords()),
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    public function articleIntents(): HasMany
    {
        return $this->hasMany(ArticleIntent::class);
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class)
            ->using(ArticleIntent::class)
            ->as('article_intent')
            ->withPivot([
                'relevance'
            ]);
    }

    public function intentPages(): HasMany
    {
        return $this->hasMany(IntentPage::class);
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class)
            ->using(IntentPage::class)
            ->as('intent_page')
            ->withPivot([
                'relevance'
            ]);
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

    public function isEmbeddable(): bool
    {
        return $this->title and $this->description;
    }

    public function isEmbedded(): bool
    {
        if (($this->isDirty('title')
            or $this->isDirty('description'))
            and !$this->isDirty('is_embedded')
        ) {
            return false;
        }

        return !!$this->is_embedded;
    }

    public function getTextForEmbedding(): ?string
    {
        return $this->title."\n".$this->description;
    }
}
