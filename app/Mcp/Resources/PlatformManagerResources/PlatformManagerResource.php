<?php

namespace App\Mcp\Resources\PlatformManagerResources;

use App\Mcp\Resources\Resource;

abstract class PlatformManagerResource extends Resource
{
    protected function namePrefix(): ?string
    {
        return 'platform-manager';
    }
}