<?php

namespace App\Services\Synthesizer;

use App\Contracts\Synthesizer\Synthesizer as SynthesizerContract;
use App\Services\Synthesizer\Author\Drivers\BasicAuthorDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\BasicBriefBuilderDriver;
use App\Services\Synthesizer\IdeaForge\Drivers\BasicIdeaForgeDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\BasicIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\BasicIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\BasicIdeaPickerDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\BasicOutlineBuilderDriver;
use Illuminate\Support\Manager;

class SynthesizerManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('synthesizer.default', 'basic');
    }

    protected function createBasicDriver(): SynthesizerContract
    {
        $ideaForge = new BasicIdeaForgeDriver(
            ideaAdvisors: [
                new BasicIdeaAdvisorDriver,
            ],
            ideaAuditor: new BasicIdeaAuditorDriver,
            ideaPicker: new BasicIdeaPickerDriver,
        );

        return new SynthesizerService(
            ideaForge: $ideaForge,
            briefBuilder: new BasicBriefBuilderDriver,
            outlineBuilder: new BasicOutlineBuilderDriver,
            author: new BasicAuthorDriver,
        );
    }
}
