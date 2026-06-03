<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\HasName;
use App\Utils\Str;
use Laravel\Mcp\Server\Tool as BaseTool;

abstract class Tool extends BaseTool
{
    use HasName;
}