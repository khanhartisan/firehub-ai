<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers;

use App\Services\Synthesizer\Support\SynthesizerOpenAICompatibleClient;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;

class OpenAICompatibleIdeaPickerDriver extends OpenAIIdeaPickerDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $merged = SynthesizerSubserviceConfig::driver('idea_picker', 'openai_compatible', $config);

        parent::__construct(
            SynthesizerOpenAICompatibleClient::client('idea_picker', $merged),
            $merged,
        );
    }
}
