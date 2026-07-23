<?php

namespace App\Models;

use App\Casts\HitlPlatformContextCast;
use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\HitlPlatformConfig;
use App\Contracts\HitlGateway\HitlPlatformManager;
use App\Enums\HitlHook;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

/**
 * @property Collection<int, HitlHook>|null $hooks
 */
class HitlPlatform extends Model implements ShouldCascade
{
    use Cascades;

    protected function casts()
    {
        return [
            'is_active' => 'boolean',
            'config' => 'array',
            'context' => HitlPlatformContextCast::class,
            'hooks' => AsEnumCollection::of(HitlHook::class),
        ];
    }

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

        if ($context = $this->context and $context instanceof SemanticContext) {
            $hitlPlatformManager->setContext($context);
        }

        return $hitlPlatformManager;
    }

    public function hitlTasks(): HasMany
    {
        return $this->hasMany(HitlTask::class);
    }
}
