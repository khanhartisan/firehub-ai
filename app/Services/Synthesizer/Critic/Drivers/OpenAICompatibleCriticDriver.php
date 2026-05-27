<?php

namespace App\Services\Synthesizer\Critic\Drivers;

use App\Services\Synthesizer\Critic\CriticManager;
use App\Services\Synthesizer\Support\SynthesizerOpenAICompatibleClient;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;

class OpenAICompatibleCriticDriver extends OpenAICriticDriver
{
    protected int $minCriticisms = 1;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(CriticManager $criticManager, string $purpose, array $config = [])
    {
        $merged = SynthesizerSubserviceConfig::driver('critic', 'openai_compatible', $config);

        parent::__construct(
            $criticManager,
            $purpose,
            SynthesizerOpenAICompatibleClient::client('critic', $merged),
            $merged,
        );
    }
}
