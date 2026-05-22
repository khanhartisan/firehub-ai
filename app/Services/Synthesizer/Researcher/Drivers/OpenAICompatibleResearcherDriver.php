<?php

namespace App\Services\Synthesizer\Researcher\Drivers;

use App\Services\Synthesizer\Support\SynthesizerOpenAICompatibleClient;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;

class OpenAICompatibleResearcherDriver extends OpenAIResearcherDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $merged = SynthesizerSubserviceConfig::driver('researcher', 'openai_compatible', $config);

        parent::__construct(
            SynthesizerOpenAICompatibleClient::responsesClient('researcher', $merged),
            $merged,
        );
    }
}
