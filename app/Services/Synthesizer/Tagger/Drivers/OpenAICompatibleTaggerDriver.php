<?php

namespace App\Services\Synthesizer\Tagger\Drivers;

use App\Services\Synthesizer\Support\SynthesizerOpenAICompatibleClient;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;

class OpenAICompatibleTaggerDriver extends OpenAITaggerDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $merged = SynthesizerSubserviceConfig::driver('tagger', 'openai_compatible', $config);

        parent::__construct(
            SynthesizerOpenAICompatibleClient::client('tagger', $merged),
            $merged,
        );
    }
}
