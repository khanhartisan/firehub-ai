<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Contracts\Synthesizer\IdeaForge\IdeaForge getIdeaForge()
 * @method static \App\Contracts\Synthesizer\Synthesizer setIdeaForge(\App\Contracts\Synthesizer\IdeaForge\IdeaForge $ideaForge)
 * @method static \App\Contracts\Synthesizer\Researcher\Researcher getResearcher()
 * @method static \App\Contracts\Synthesizer\Synthesizer setResearcher(\App\Contracts\Synthesizer\Researcher\Researcher $researcher)
 * @method static \App\Contracts\Synthesizer\Synthesizer setBriefBuilder(\App\Contracts\Synthesizer\BriefBuilder\BriefBuilder $builder)
 * @method static \App\Contracts\Synthesizer\BriefBuilder\BriefBuilder getBriefBuilder()
 * @method static \App\Contracts\Synthesizer\Synthesizer setOutlineBuilder(\App\Contracts\Synthesizer\OutlineBuilder\OutlineBuilder $builder)
 * @method static \App\Contracts\Synthesizer\OutlineBuilder\OutlineBuilder getOutlineBuilder()
 * @method static \App\Contracts\Synthesizer\Synthesizer setWriter(\App\Contracts\Synthesizer\Writer\Writer $writer)
 * @method static \App\Contracts\Synthesizer\Writer\Writer getWriter()
 * @method static \App\Contracts\Synthesizer\Synthesizer driver(string|null $driver = null)
 *
 * @see \App\Services\Synthesizer\SynthesizerManager
 */
class Synthesizer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'synthesizer.manager';
    }
}
