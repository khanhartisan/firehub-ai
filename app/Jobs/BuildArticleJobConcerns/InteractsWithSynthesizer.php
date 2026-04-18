<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Synthesizer\Synthesizer as SynthesizerContract;
use App\Facades\Synthesizer;

/**
 * Resolves the default synthesizer driver once per job run ({@see Synthesizer::driver()}).
 * Brief/outline/author and idea-forge all hang off the same {@see SynthesizerContract} instance.
 */
trait InteractsWithSynthesizer
{
    protected ?SynthesizerContract $resolvedSynthesizerDriver = null;

    protected function synthesizer(): SynthesizerContract
    {
        return $this->resolvedSynthesizerDriver ??= Synthesizer::driver();
    }
}
