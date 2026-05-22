<?php

namespace App\Services\Synthesizer\BriefBuilder\Drivers;

use App\Services\Synthesizer\Support\SynthesizerOpenAICompatibleClient;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;

class OpenAICompatibleBriefBuilderDriver extends OpenAIBriefBuilderDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $merged = SynthesizerSubserviceConfig::driver('brief_builder', 'openai_compatible', $config);

        parent::__construct(
            SynthesizerOpenAICompatibleClient::responsesClient('brief_builder', $merged),
            $merged,
        );
    }
}
