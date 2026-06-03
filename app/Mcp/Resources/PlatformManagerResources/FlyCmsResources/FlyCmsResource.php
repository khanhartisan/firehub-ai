<?php

namespace App\Mcp\Resources\PlatformManagerResources\FlyCmsResources;

use App\Mcp\Resources\PlatformManagerResources\PlatformManagerResource;

abstract class FlyCmsResource extends PlatformManagerResource
{
    protected function namePrefix(): ?string
    {
        return parent::namePrefix().'--flycms--';
    }
}