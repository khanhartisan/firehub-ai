<?php

namespace App\Mcp\Tools;

use App\Utils\Str;
use Laravel\Mcp\Server\Tool as BaseTool;

abstract class Tool extends BaseTool
{
    public function name(): string
    {
        $name = parent::name();
        if ($prefix = $this->namePrefix()) {
            $name = Str::endsWith($prefix, '-') ? $prefix.$name : $prefix.'-'.$name;
        }
        return $name;
    }

    protected function namePrefix(): ?string
    {
        return null;
    }
}