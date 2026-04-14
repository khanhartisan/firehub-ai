<?php

namespace App\Services\Synthesizer\BriefBuilders;

use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Services\Synthesizer\BriefBuilderService;

class OpenAIBriefBuilderDriver extends BriefBuilderService
{
    public function conceive(string $clientId, ?string $prompt = null): Brief
    {
        // TODO: Implement conceive() method.
    }
}