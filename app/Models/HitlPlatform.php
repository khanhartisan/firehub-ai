<?php

namespace App\Models;

use App\Casts\SemanticContextCast;
use App\Contracts\HitlGateway\HitlPlatformConfig;
use App\Contracts\HitlGateway\HitlPlatformManager;
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

    public function getHitlPlatformManager(): HitlPlatformManager
    {
        $hitlPlatformManager = \App\Facades\HitlGateway\HitlPlatformManager::driver($this->driver);

        if ($this->config and $config = HitlPlatformConfig::fromArray($this->config)) {
            $hitlPlatformManager->setConfig($config);
        }

        return $hitlPlatformManager;
    }

    public function hitlTasks(): HasMany
    {
        return $this->hasMany(HitlTask::class);
    }
}
