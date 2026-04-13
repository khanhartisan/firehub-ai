<?php

namespace App\Contracts\Synthesizer;

interface BriefBuilder
{
    public function conceive(string $clientId, ?string $prompt = null): Brief;
}