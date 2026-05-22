<?php

namespace App\Services\Synthesizer\Writer\Drivers;

use App\Services\Synthesizer\Support\SynthesizerOpenAICompatibleClient;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;

class OpenAICompatibleWriterDriver extends OpenAIWriterDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $merged = SynthesizerSubserviceConfig::driver('writer', 'openai_compatible', $config);

        parent::__construct(
            SynthesizerOpenAICompatibleClient::client('writer', $merged),
            $merged,
        );
    }
}
