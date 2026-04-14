<?php

namespace App\Contracts\Synthesizer\BriefBuilder;

interface BriefBuilder
{
    public function conceive(string $clientId, ?string $prompt = null): Brief;
}