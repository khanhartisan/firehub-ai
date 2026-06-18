<?php

namespace App\Models;

use App\Contracts\Model\PageCountable as PageCountableContract;
use App\Models\Concerns\PageCountable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Tag extends Model implements PageCountableContract, ShouldCascade
{
    use Cascades;
    use PageCountable;

    protected $fillable = [
        'name',
    ];

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->hasMany(PageTag::class)),
            new CascadeDetails($this->hasMany(ArticleTag::class)),
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class)
            ->using(PageTag::class)
            ->as('page_tag');
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class)
            ->using(ArticleTag::class)
            ->as('article_tag');
    }
}
