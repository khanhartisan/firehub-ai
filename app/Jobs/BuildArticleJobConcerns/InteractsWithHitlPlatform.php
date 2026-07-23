<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\HitlGateway\HitlPlatformManager;
use App\Enums\HitlHook;
use App\Models\HitlPlatform;

trait InteractsWithHitlPlatform
{
    protected ?HitlPlatform $hitlPlatform = null;

    protected function getHitlPlatform(): ?HitlPlatform
    {
        return $this->hitlPlatform ??= $this->article?->client?->hitlPlatform;
    }

    protected function getHitlPlatformManager(): ?HitlPlatformManager
    {
        return $this->getHitlPlatform()?->getHitlPlatformManager();
    }

    protected function hasHitlHook(HitlHook $hook): bool
    {
        if (! $hitlPlatform = $this->getHitlPlatform()
            or ! $this->getHitlPlatformManager()
            or ! $hooks = $hitlPlatform->hooks
        ) {
            return false;
        }

        return $hooks->contains($hook);
    }
}
