<?php

namespace App\Models;

use App\Casts\ClientContextCast;
use App\Enums\Language;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Client extends Model implements ShouldCascade
{
    use Cascades;

    protected $casts = [
        'language' => Language::class,
        'context' => ClientContextCast::class,
    ];

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->articles())
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
