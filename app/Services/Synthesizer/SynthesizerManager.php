<?php

namespace App\Services\Synthesizer;

use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;
use App\Contracts\Synthesizer\Synthesizer as SynthesizerContract;
use App\Services\Synthesizer\BriefBuilder\BriefBuilderManager;
use App\Services\Synthesizer\Critic\CriticManager;
use App\Services\Synthesizer\Editor\EditorManager;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\IdeaAdvisorManager;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\IdeaAuditorManager;
use App\Services\Synthesizer\IdeaForge\IdeaForgeManager;
use App\Services\Synthesizer\IdeaForge\IdeaForgeService;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\IdeaPickerManager;
use App\Services\Synthesizer\Illustration\Director\IllustrationDirectorManager;
use App\Services\Synthesizer\Illustration\Illustrator\IllustratorManager;
use App\Services\Synthesizer\OutlineBuilder\OutlineBuilderManager;
use App\Services\Synthesizer\Researcher\ResearcherManager;
use App\Services\Synthesizer\Writer\WriterManager;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;

class SynthesizerManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('synthesizer.default', 'basic');
    }

    protected function createDriver($driver): SynthesizerContract
    {
        $method = 'create'.Str::studly($driver).'Driver';

        if (method_exists($this, $method)) {
            return $this->{$method}();
        }

        return $this->createConfiguredDriver($driver);
    }

    protected function createBasicDriver(): SynthesizerContract
    {
        return $this->createConfiguredDriver('basic');
    }

    protected function createOpenaiDriver(): SynthesizerContract
    {
        return $this->createConfiguredDriver('openai');
    }

    protected function createOpenaiCompatibleDriver(): SynthesizerContract
    {
        return $this->createConfiguredDriver('openai_compatible');
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
            $ideaForgeConfig['advisors'] ?? [['driver' => 'basic']]
        );
        $ideaAuditor = $this->container->make(IdeaAuditorManager::class)->driver(
            $this->resolveDriverName($ideaForgeConfig['auditor'] ?? null, 'basic')
        );
        $ideaPicker = $this->container->make(IdeaPickerManager::class)->driver(
            $this->resolveDriverName($ideaForgeConfig['picker'] ?? null, 'basic')
        );

        /** @var IdeaForgeService $ideaForge */
        $ideaForge = $this->container->make(IdeaForgeManager::class)->driver(
            $this->resolveDriverName($ideaForgeConfig['driver'] ?? $ideaForgeConfig, 'basic')
        );
        $ideaForge->setIdeaAdvisors($ideaAdvisors);
        $ideaForge->setIdeaAuditor($ideaAuditor);
        $ideaForge->setIdeaPicker($ideaPicker);

        $briefBuilder = $this->container->make(BriefBuilderManager::class)->driver(
            $this->resolveDriverName($driverConfig['brief_builder'] ?? null, 'basic')
        );
        $researcher = $this->container->make(ResearcherManager::class)->driver(
            $this->resolveDriverName($driverConfig['researcher'] ?? null, 'basic')
        );
        $outlineBuilder = $this->container->make(OutlineBuilderManager::class)->driver(
            $this->resolveDriverName($driverConfig['outline_builder'] ?? null, 'basic')
        );
        $editor = $this->container->make(EditorManager::class)->driver(
            $this->resolveDriverName($driverConfig['editor'] ?? null, 'basic')
        );
        $criticsConfig = $driverConfig['critics'] ?? [];
        $critics = $this->makeCriticsFromConfig($criticsConfig);
        $writer = $this->container->make(WriterManager::class)->driver(
            $this->resolveDriverName($driverConfig['writer'] ?? null, 'basic')
        );

        $illustrationConfig = $driverConfig['illustration'] ?? [];
        $illustrationDirector = $this->container->make(IllustrationDirectorManager::class)->driver(
            $this->resolveDriverName($illustrationConfig['director'] ?? null, 'basic')
        );
        $illustratorEntries = $illustrationConfig['illustrators'] ?? ['basic'];
        $illustrators = array_values(array_map(
            fn (mixed $entry) => $this->container->make(IllustratorManager::class)->driver(
                $this->resolveDriverName($entry, 'basic')
            ),
            $illustratorEntries
        ));

        return $this->container->make(SynthesizerService::class, [
            'ideaForge' => $ideaForge,
            'researcher' => $researcher,
            'briefBuilder' => $briefBuilder,
            'outlineBuilder' => $outlineBuilder,
            'editor' => $editor,
            'critics' => $critics,
            'writer' => $writer,
            'illustrationDirector' => $illustrationDirector,
            'illustrators' => $illustrators,
        ]);
    }

    /**
     * @param  list<array{driver: string, purpose: string, order?: int, max_rectification_rounds?: int|null}>|string|null  $config
     * @return list<\App\Contracts\Synthesizer\Critic\Critic>
     */
    protected function makeCriticsFromConfig(mixed $config): array
    {
        $manager = $this->container->make(CriticManager::class);

        if (is_string($config) && $config !== '') {
            return $manager->getCritics($this->resolveDriverName($config, 'basic'));
        }

        if (! is_array($config) || $config === []) {
            return $manager->getCritics('basic');
        }

        $critics = [];

        foreach ($config as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $driver = $this->resolveDriverName($entry, 'basic');
            $purpose = $entry['purpose'] ?? null;

            if (! is_string($purpose) || $purpose === '') {
                throw new \InvalidArgumentException('Critic config entry must include a "purpose".');
            }

            $order = array_key_exists('order', $entry)
                ? max(0, (int) $entry['order'])
                : $index;

            $critics[] = $manager
                ->makeCritic($purpose, $driver)
                ->setOrder($order);
        }

        return array_values($critics);
    }

    /**
     * @param  list<array{driver: string, weight?: float|int|string}>  $entries
     * @return list<IdeaAdvisor>
     */
    protected function makeIdeaAdvisorsFromConfig(array $entries): array
    {
        $manager = $this->container->make(IdeaAdvisorManager::class);
        $advisors = [];

        foreach ($entries as $entry) {
            $advisors[] = $this->makeIdeaAdvisorFromConfigEntry($manager, $entry);
        }

        return $advisors;
    }

    /**
     * @param  array{driver: string, weight?: float|int|string}  $entry
     */
    protected function makeIdeaAdvisorFromConfigEntry(IdeaAdvisorManager $manager, array $entry): IdeaAdvisor
    {
        $driverName = $this->resolveDriverName($entry, '');
        if ($driverName === '') {
            throw new \InvalidArgumentException(
                'Idea advisor config must include a "driver" name.'
            );
        }

        $advisor = $manager->driver($driverName);
        if (! $advisor instanceof IdeaAdvisor) {
            throw new \InvalidArgumentException(sprintf(
                'Idea advisor driver "%s" must implement %s.',
                $driverName,
                IdeaAdvisor::class
            ));
        }

        if (array_key_exists('weight', $entry)) {
            $advisor->setWeight((float) $entry['weight']);
        }

        return $advisor;
    }

    protected function resolveDriverName(mixed $config, string $default): string
    {
        if (is_string($config) && $config !== '') {
            return $this->normalizeDriverName($config);
        }

        if (is_array($config) && isset($config['driver']) && is_string($config['driver']) && $config['driver'] !== '') {
            return $this->normalizeDriverName($config['driver']);
        }

        return $default;
    }

    /**
     * Map legacy orchestrator config that stored implementation class names.
     */
    protected function normalizeDriverName(string $name): string
    {
        if (! str_contains($name, '\\')) {
            return $name;
        }

        $base = class_basename($name);

        return match ($base) {
            'OpenAIDebugIllustratorDriver' => 'debug',
            'OpenAIIdeaExpansionAdvisorDriver' => 'openai_expansion',
            'OpenAICompatibleIdeaExpansionAdvisorDriver' => 'openai_compatible_expansion',
            default => str_starts_with($base, 'OpenAICompatible') ? 'openai_compatible'
                : (str_starts_with($base, 'OpenAI') ? 'openai' : 'basic'),
        };
    }
}
