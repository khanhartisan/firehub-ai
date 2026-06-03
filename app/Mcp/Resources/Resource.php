<?php

namespace App\Mcp\Resources;

use App\Mcp\Concerns\HasName;
use Laravel\Mcp\Server\Resource as BaseResource;

abstract class Resource extends BaseResource
{
    use HasName;
}