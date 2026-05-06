<?php

namespace App\Services\Synthesizer;

use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;
use App\Contracts\Synthesizer\Synthesizer as SynthesizerContract;
use App\Services\Synthesizer\Writer\Drivers\BasicWriterDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\BasicBriefBuilderDriver;
use App\Services\Synthesizer\IdeaForge\Drivers\BasicIdeaForgeDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\BasicIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\BasicIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\BasicIdeaPickerDriver;
use App\Services\Synthesizer\Illustration\Director\Drivers\BasicDirectorDriver;
use App\Services\Synthesizer\Illustration\Illustrator\Drivers\BasicIllustratorDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\BasicOutlineBuilderDriver;
use App\Services\Synthesizer\Researcher\Drivers\BasicResearcherDriver;
use Illuminate\Support\Manager;

class SynthesizerManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('synthesizer.default', 'basic');
    }

    protected function createDriver($driver): SynthesizerContract
    {
        return $this->createConfiguredDriver($driver);
    }

    protected function createConfiguredDriver(string $driver): SynthesizerContract
    {
        $driverConfig = $this->config->get("synthesizer.drivers.{$driver}", []);

        return $this->buildSynthesizer($driverConfig);
    }

    protected function buildSynthesizer(array $driverConfig): SynthesizerContract
    {
        $ideaForgeConfig = $driverConfig['idea_forge'] ?? [];
        $ideaAdvisors = $this->makeIdeaAdvisorsFromConfig(
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

        $researcher = $this->container->make(
            $driverConfig['researcher']['driver'] ?? BasicResearcherDriver::class
        );

        $outlineBuilder = $this->container->make(
            $driverConfig['outline_builder']['driver'] ?? BasicOutlineBuilderDriver::class
        );

        $writer = $this->container->make(
            $driverConfig['author']['driver'] ?? BasicWriterDriver::class
        );

        $illustrationDirector = $this->container->make(
            $driverConfig['illustration']['director'] ?? BasicDirectorDriver::class
        );
        $illustrators = array_values(array_map(
            fn (string $driver) => $this->container->make($driver),
            $driverConfig['illustration']['illustrators'] ?? [BasicIllustratorDriver::class]
        ));

        /** @var SynthesizerContract */
        return $this->container->make(
            $driverConfig['service'] ?? SynthesizerService::class,
            [
                'ideaForge' => $ideaForge,
                'researcher' => $researcher,
                'briefBuilder' => $briefBuilder,
                'outlineBuilder' => $outlineBuilder,
                'writer' => $writer,
                'illustrationDirector' => $illustrationDirector,
                'illustrators' => $illustrators,
            ]
        );
    }

    /**
     * @param  list<string|array{class: string, weight?: float|int|string}>  $entries
     * @return list<IdeaAdvisor>
     */
    protected function makeIdeaAdvisorsFromConfig(array $entries): array
    {
        $advisors = [];
        foreach ($entries as $entry) {
            $advisors[] = $this->makeIdeaAdvisorFromConfigEntry($entry);
        }

        return $advisors;
    }

    /**
     * @param  string|array{class: string, weight?: float|int|string}  $entry
     */
    protected function makeIdeaAdvisorFromConfigEntry(string|array $entry): IdeaAdvisor
    {
        if (is_string($entry)) {
            $advisor = $this->container->make($entry);
            if (! $advisor instanceof IdeaAdvisor) {
                throw new \InvalidArgumentException(sprintf(
                    'Idea advisor class "%s" must implement %s.',
                    $entry,
                    IdeaAdvisor::class
                ));
            }

            return $advisor;
        }

        if (! is_array($entry) || ! isset($entry['class']) || ! is_string($entry['class'])) {
            throw new \InvalidArgumentException(
                'Idea advisor config must be a class string or an array with a string "class" key.'
            );
        }

        $advisor = $this->container->make($entry['class']);
        if (! $advisor instanceof IdeaAdvisor) {
            throw new \InvalidArgumentException(sprintf(
                'Idea advisor class "%s" must implement %s.',
                $entry['class'],
                IdeaAdvisor::class
            ));
        }

        if (array_key_exists('weight', $entry)) {
            $advisor->setWeight((float) $entry['weight']);
        }

        return $advisor;
    }
}
