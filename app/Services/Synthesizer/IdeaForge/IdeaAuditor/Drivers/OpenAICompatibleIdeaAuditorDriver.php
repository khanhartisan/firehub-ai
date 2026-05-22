<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers;

use App\Services\Synthesizer\Support\SynthesizerOpenAICompatibleClient;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;

class OpenAICompatibleIdeaAuditorDriver extends OpenAIIdeaAuditorDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $merged = SynthesizerSubserviceConfig::driver('idea_auditor', 'openai_compatible', $config);

        parent::__construct(
            SynthesizerOpenAICompatibleClient::responsesClient('idea_auditor', $merged),
            $merged,
        );
    }
}
