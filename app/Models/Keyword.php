<?php

namespace App\Models;

use App\Casts\KeywordSearchEngineDataCast;
use App\Contracts\CommonData\Keyword as KeywordData;
use App\Enums\Country;
use App\Enums\KeywordStatus;
use App\Enums\Language;
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
        'language' => Language::class,
        'country' => Country::class,
        'status' => KeywordStatus::class,
        'volume' => 'integer',
        'difficulty' => 'float',
        'intents_count' => 'integer',
        'pages_count' => 'integer',
        'search_engine_data' => KeywordSearchEngineDataCast::class,
        'deleted_at' => 'datetime',
        'researched_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function toKeywordData(): KeywordData
    {
        return new KeywordData(Str::sanitizeKeyword($this->keyword))
            ->setCountry($this->country)
            ->setLanguage($this->language);
    }

    public function generateHash(): string
    {
        return sha1(
            Str::sanitizeKeyword($this->keyword)
            .'@'.$this->language?->value
            .'@'.$this->country?->value
        );
    }

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->hasMany(IntentKeyword::class)),
            new CascadeDetails($this->hasMany(KeywordPage::class)),
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
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

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class)
            ->using(KeywordPage::class)
            ->as('keyword_page')
            ->withPivot([
                'relevance'
            ]);
    }
}
