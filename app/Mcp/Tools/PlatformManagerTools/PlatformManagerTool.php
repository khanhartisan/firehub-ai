<?php

namespace App\Mcp\Tools\PlatformManagerTools;

use App\Mcp\Tools\Tool;

abstract class PlatformManagerTool extends Tool
{
    protected function namePrefix(): ?string
    {
        return 'platform-manager';
    }
}