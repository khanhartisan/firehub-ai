<?php

namespace App\Contracts;

interface Clonable
{
    public function clone(): self;
}