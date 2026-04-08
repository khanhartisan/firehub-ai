<?php

namespace App\Contracts\Synthesizer;

interface Author
{
    public function draft(Brief $brief, Outline $outline, ?string $prompt = null): Draft;
}