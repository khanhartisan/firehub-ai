<?php

namespace App\Contracts\Synthesizer;

interface BriefBuilder
{
    public function conceive(string $space, ?string $prompt = null): Brief;
}