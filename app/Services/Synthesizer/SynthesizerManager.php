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
use App\Services\Synthesizer\Editor\Drivers\BasicEditorDriver;
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
            $ideaForgeConfig['advisors'] ?? [['driver' => BasicIdeaAdvisorDriver::class]]
        );
        $ideaAuditor = $this->container->make(
            $this->resolveImplementationDriver($ideaForgeConfig['auditor'] ?? null, BasicIdeaAuditorDriver::class)
        );
        $ideaPicker = $this->container->make(
            $this->resolveImplementationDriver($ideaForgeConfig['picker'] ?? null, BasicIdeaPickerDriver::class)
        );

        $ideaForge = $this->container->make(
            $this->resolveImplementationDriver($ideaForgeConfig, BasicIdeaForgeDriver::class),
            [
                'ideaAdvisors' => $ideaAdvisors,
                'ideaAuditor' => $ideaAuditor,
                'ideaPicker' => $ideaPicker,
            ]
        );

        $briefBuilder = $this->container->make(
            $this->resolveImplementationDriver($driverConfig['brief_builder'] ?? null, BasicBriefBuilderDriver::class)
        );

        $researcher = $this->container->make(
            $this->resolveImplementationDriver($driverConfig['researcher'] ?? null, BasicResearcherDriver::class)
        );

        $outlineBuilder = $this->container->make(
            $this->resolveImplementationDriver($driverConfig['outline_builder'] ?? null, BasicOutlineBuilderDriver::class)
        );

        $editor = $this->container->make(
            $this->resolveImplementationDriver($driverConfig['editor'] ?? null, BasicEditorDriver::class)
        );

        $writer = $this->container->make(
            $this->resolveImplementationDriver($driverConfig['author'] ?? null, BasicWriterDriver::class)
        );

        $illustrationConfig = $driverConfig['illustration'] ?? [];
        $illustrationDirector = $this->container->make(
            $this->resolveImplementationDriver($illustrationConfig['director'] ?? null, BasicDirectorDriver::class)
        );
        $illustrators = array_values(array_map(
            fn (array $entry) => $this->container->make(
                $this->resolveImplementationDriver($entry, BasicIllustratorDriver::class)
            ),
            $illustrationConfig['illustrators'] ?? [['driver' => BasicIllustratorDriver::class]]
        ));

        /** @var SynthesizerContract */
        return $this->container->make(
            $this->resolveImplementationDriver($driverConfig['service'] ?? null, SynthesizerService::class),
            [
                'ideaForge' => $ideaForge,
                'researcher' => $researcher,
                'briefBuilder' => $briefBuilder,
                'outlineBuilder' => $outlineBuilder,
                'editor' => $editor,
                'writer' => $writer,
                'illustrationDirector' => $illustrationDirector,
                'illustrators' => $illustrators,
            ]
        );
    }

    /**
     * @param  list<array{driver: string, weight?: float|int|string}>  $entries
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
     * @param  array{driver: string, weight?: float|int|string}  $entry
     */
    protected function makeIdeaAdvisorFromConfigEntry(array $entry): IdeaAdvisor
    {
        $class = $this->resolveImplementationDriver($entry, '');
        if ($class === '') {
            throw new \InvalidArgumentException(
                'Idea advisor config must be an array with a string "driver" key.'
            );
        }

        $advisor = $this->container->make($class);
        if (! $advisor instanceof IdeaAdvisor) {
            throw new \InvalidArgumentException(sprintf(
                'Idea advisor class "%s" must implement %s.',
                $class,
                IdeaAdvisor::class
            ));
        }

        if (array_key_exists('weight', $entry)) {
            $advisor->setWeight((float) $entry['weight']);
        }

        return $advisor;
    }

    /**
     * @param  array{driver?: string}|null  $config
     */
    protected function resolveImplementationDriver(?array $config, string $default): string
    {
        if (is_array($config) && isset($config['driver']) && is_string($config['driver']) && $config['driver'] !== '') {
            return $config['driver'];
        }

        return $default;
    }
}
