<?php

namespace App\Services\Synthesizer\Editor\Drivers;

use App\Services\Synthesizer\Support\SynthesizerOpenAICompatibleClient;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;

class OpenAICompatibleEditorDriver extends OpenAIEditorDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $merged = SynthesizerSubserviceConfig::driver('editor', 'openai_compatible', $config);

        parent::__construct(
            SynthesizerOpenAICompatibleClient::client('editor', $merged),
            $merged,
        );
    }
}
