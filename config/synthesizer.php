<?php

use App\Services\Synthesizer\Author\Drivers\BasicAuthorDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\BasicBriefBuilderDriver;
use App\Services\Synthesizer\IdeaForge\Drivers\BasicIdeaForgeDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\BasicIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\BasicIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\BasicIdeaPickerDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\BasicOutlineBuilderDriver;
use App\Services\Synthesizer\SynthesizerService;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Synthesizer Driver
    |--------------------------------------------------------------------------
    |
    | Select the default orchestrator driver.
    |
    */

    'default' => env('SYNTHESIZER_DRIVER', 'basic'),

    /*
    |--------------------------------------------------------------------------
    | Synthesizer Drivers
    |--------------------------------------------------------------------------
    |
    | Configure available synthesizer drivers. The "basic" driver is a local,
    | deterministic implementation suitable for development and tests.
    |
    */

    'drivers' => [
        'basic' => [
            'service' => SynthesizerService::class,
            'idea_forge' => [
                'driver' => BasicIdeaForgeDriver::class,
                'advisors' => [
                    BasicIdeaAdvisorDriver::class,
                ],
                'auditor' => BasicIdeaAuditorDriver::class,
                'picker' => BasicIdeaPickerDriver::class,
            ],
            'brief_builder' => [
                'driver' => BasicBriefBuilderDriver::class,
            ],
            'outline_builder' => [
                'driver' => BasicOutlineBuilderDriver::class,
            ],
            'author' => [
                'driver' => BasicAuthorDriver::class,
            ],
        ],
    ],
];
