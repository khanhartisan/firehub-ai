<?php

namespace App\Contracts\Synthesizer;

interface OutlineBuilder
{
    public function outline(Brief $brief, ?string $prompt): Outline;
}