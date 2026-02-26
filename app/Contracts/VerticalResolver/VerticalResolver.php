<?php

namespace App\Contracts\VerticalResolver;

interface VerticalResolver
{
    public function resolve(string $content): VerticalResolution;
}