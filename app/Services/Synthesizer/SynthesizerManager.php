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
        return $this->createConfiguredDriver('basic');
    }

    protected function createConfiguredDriver(string $driver): SynthesizerContract
    {
        $driverConfig = $this->config->get("synthesizer.drivers.{$driver}", []);

        return $this->buildSynthesizer($driverConfig);
    }

    protected function buildSynthesizer(array $driverConfig): SynthesizerContract
    {
        $ideaForgeConfig = $driverConfig['idea_forge'] ?? [];
        $ideaAdvisors = array_map(
            fn (string $advisorClass) => $this->container->make($advisorClass),
            $ideaForgeConfig['advisors'] ?? [BasicIdeaAdvisorDriver::class]
        );
        $ideaAuditor = $this->container->make($ideaForgeConfig['auditor'] ?? BasicIdeaAuditorDriver::class);
        $ideaPicker = $this->container->make($ideaForgeConfig['picker'] ?? BasicIdeaPickerDriver::class);

        $ideaForge = $this->container->make(
            $ideaForgeConfig['driver'] ?? BasicIdeaForgeDriver::class,
            [
                'ideaAdvisors' => $ideaAdvisors,
                'ideaAuditor' => $ideaAuditor,
                'ideaPicker' => $ideaPicker,
            ]
        );

        $briefBuilder = $this->container->make(
            $driverConfig['brief_builder']['driver'] ?? BasicBriefBuilderDriver::class
        );

        $outlineBuilder = $this->container->make(
            $driverConfig['outline_builder']['driver'] ?? BasicOutlineBuilderDriver::class
        );

        $author = $this->container->make(
            $driverConfig['author']['driver'] ?? BasicAuthorDriver::class
        );

        /** @var SynthesizerContract */
        return $this->container->make(
            $driverConfig['service'] ?? SynthesizerService::class,
            [
                'ideaForge' => $ideaForge,
                'briefBuilder' => $briefBuilder,
                'outlineBuilder' => $outlineBuilder,
                'author' => $author,
            ]
        );
    }
}
