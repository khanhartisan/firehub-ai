<?php

namespace App\Mcp\Concerns;

use App\Utils\Str;

trait HasName
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