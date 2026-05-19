<?php

namespace App\Services\Synthesizer\IdeaForge;

use App\Services\Synthesizer\IdeaForge\Drivers\BasicIdeaForgeDriver;
use App\Services\Synthesizer\Support\SubserviceManager;

class IdeaForgeManager extends SubserviceManager
{
    protected function configKey(): string
    {
        return 'idea_forge';
    }

    protected function createBasicDriver(): BasicIdeaForgeDriver
    {
        return new BasicIdeaForgeDriver;
    }
}
