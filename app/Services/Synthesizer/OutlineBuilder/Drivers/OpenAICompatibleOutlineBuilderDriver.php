<?php

namespace App\Services\Synthesizer\OutlineBuilder\Drivers;

use App\Services\Synthesizer\Support\SynthesizerOpenAICompatibleClient;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;

class OpenAICompatibleOutlineBuilderDriver extends OpenAIOutlineBuilderDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $merged = SynthesizerSubserviceConfig::driver('outline_builder', 'openai_compatible', $config);

        parent::__construct(
            SynthesizerOpenAICompatibleClient::responsesClient('outline_builder', $merged),
            $merged,
        );
    }
}
