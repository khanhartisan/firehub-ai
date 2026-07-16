<?php

namespace App\Models;

use App\Casts\SemanticContextCast;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class HitlPlatform extends Model implements ShouldCascade
{
    use Cascades;

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
        'context' => SemanticContextCast::class,
    ];

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->hitlTasks())
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    public function hitlTasks(): HasMany
    {
        return $this->hasMany(HitlTask::class);
    }
}
