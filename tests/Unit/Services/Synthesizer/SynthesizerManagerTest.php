<?php

namespace Tests\Unit\Services\Synthesizer;

use App\Contracts\Synthesizer\Author\Draft;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Facades\Synthesizer as SynthesizerFacade;
use App\Services\Synthesizer\Author\Drivers\BasicAuthorDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\BasicBriefBuilderDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\OpenAIBriefBuilderDriver;
use App\Services\Synthesizer\IdeaForge\Drivers\BasicIdeaForgeDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\BasicIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\BasicIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\BasicIdeaPickerDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\BasicOutlineBuilderDriver;
use App\Services\Synthesizer\SynthesizerManager;
use App\Services\Synthesizer\SynthesizerService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SynthesizerManagerTest extends TestCase
{
    public function test_facade_returns_synthesizer_manager_instance(): void
    {
        $manager = SynthesizerFacade::getFacadeRoot();

        $this->assertInstanceOf(SynthesizerManager::class, $manager);
    }

    public function test_it_returns_basic_driver_by_default(): void
    {
        Config::set('synthesizer.default', 'basic');

        $manager = SynthesizerFacade::getFacadeRoot();
        $driver = $manager->driver();

        $this->assertInstanceOf(SynthesizerService::class, $driver);
        $this->assertInstanceOf(BasicIdeaForgeDriver::class, $driver->getIdeaForge());
        $this->assertInstanceOf(BasicBriefBuilderDriver::class, $driver->getBriefBuilder());
        $this->assertInstanceOf(BasicOutlineBuilderDriver::class, $driver->getOutlineBuilder());
        $this->assertInstanceOf(BasicAuthorDriver::class, $driver->getAuthor());

        $ideaForge = $driver->getIdeaForge();
        $this->assertNotEmpty($ideaForge->getIdeaAdvisors());
        $this->assertInstanceOf(BasicIdeaAdvisorDriver::class, $ideaForge->getIdeaAdvisors()[0]);
        $this->assertInstanceOf(BasicIdeaAuditorDriver::class, $ideaForge->getIdeaAuditor());
        $this->assertInstanceOf(BasicIdeaPickerDriver::class, $ideaForge->getIdeaPicker());
    }

    public function test_basic_driver_generates_brief_outline_and_draft_from_brainstormed_idea(): void
    {
        $driver = SynthesizerFacade::driver('basic');
        $ideaForge = $driver->getIdeaForge();

        /** @var BasicIdeaAdvisorDriver $advisor */
        $advisor = $ideaForge->getIdeaAdvisors()[0];

        $context = 'Latest AI coding tools pricing and adoption trends';
        $temporalSuggestions = $advisor->suggestTemporal('client-1', $context);
        $intentTypeSuggestions = $advisor->suggestIntentTypes('client-1', $context);
        $ideas = $advisor->brainstorm($temporalSuggestions, $intentTypeSuggestions, $context, 3);

        $this->assertNotEmpty($ideas);

        $auditReport = $ideaForge->getIdeaAuditor()->audit($ideas[0]);
        $pickedReports = $ideaForge->getIdeaPicker()->pick([$auditReport], $context, 1);

        $this->assertNotNull($pickedReports);
        $this->assertCount(1, $pickedReports);

        $idea = $pickedReports[0]->getIdea();
        $brief = $driver->getBriefBuilder()->conceive($idea, $context);
        $outline = $driver->getOutlineBuilder()->outline($brief, 'Include a section about trade-offs');
        $draft = $driver->getAuthor()->draft($brief, $outline, 'Keep tone practical and concise.');

        $this->assertInstanceOf(Brief::class, $brief);
        $this->assertInstanceOf(Outline::class, $outline);
        $this->assertInstanceOf(Draft::class, $draft);
        $this->assertNotEmpty($brief->getTitle());
        $this->assertNotEmpty($outline->getItems());
        $this->assertStringContainsString('## Introduction', (string) $draft->getBodyMarkdown());
    }

    public function test_it_selects_sub_service_driver_from_configuration(): void
    {
        Config::set('synthesizer.drivers.basic.brief_builder.driver', OpenAIBriefBuilderDriver::class);

        $driver = SynthesizerFacade::driver('basic');

        $this->assertInstanceOf(OpenAIBriefBuilderDriver::class, $driver->getBriefBuilder());
    }
}
