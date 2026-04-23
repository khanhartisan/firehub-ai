<?php

namespace App\Contracts\FactChecker;

interface FactCheckable
{
    public function getFactClaim(): string;
}