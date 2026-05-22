<?php

namespace App\Services\Synthesizer\Illustration\Director\Drivers;

use App\Services\Synthesizer\Support\SynthesizerOpenAICompatibleClient;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;

class OpenAICompatibleDirectorDriver extends OpenAIDirectorDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $merged = SynthesizerSubserviceConfig::driver('illustration_director', 'openai_compatible', $config);

        parent::__construct(
            SynthesizerOpenAICompatibleClient::client('illustration_director', $merged),
            $merged,
        );
    }
}
