<?php

namespace Tests\Unit\Services\Synthesizer;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\Writer\Draft;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Facades\Synthesizer as SynthesizerFacade;
use App\Services\Synthesizer\Writer\Drivers\BasicWriterDriver;
use App\Services\Synthesizer\Writer\Drivers\OpenAIWriterDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\BasicBriefBuilderDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\OpenAIBriefBuilderDriver;
use App\Services\Synthesizer\IdeaForge\Drivers\BasicIdeaForgeDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\BasicIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\OpenAICompatibleIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\OpenAICompatibleIdeaExpansionAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\OpenAIIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\BasicIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\OpenAICompatibleIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\OpenAIIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\BasicIdeaPickerDriver;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\OpenAICompatibleIdeaPickerDriver;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\OpenAIIdeaPickerDriver;
use App\Services\Synthesizer\Illustration\Director\Drivers\BasicDirectorDriver;
use App\Services\Synthesizer\Illustration\Director\Drivers\OpenAIDirectorDriver;
use App\Services\Synthesizer\Illustration\Illustrator\Drivers\BasicIllustratorDriver;
use App\Services\Synthesizer\Illustration\Illustrator\Drivers\OpenAIIllustratorDriver;
use App\Services\Synthesizer\Critic\Drivers\BasicCriticDriver;
use App\Services\Synthesizer\Critic\Drivers\OpenAICriticDriver;
use App\Services\Synthesizer\Editor\Drivers\BasicEditorDriver;
use App\Services\Synthesizer\Editor\Drivers\OpenAIEditorDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\BasicOutlineBuilderDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\OpenAIOutlineBuilderDriver;
use App\Services\Synthesizer\Researcher\Drivers\BasicResearcherDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\OpenAICompatibleBriefBuilderDriver;
use App\Services\Synthesizer\Editor\Drivers\OpenAICompatibleEditorDriver;
use App\Services\Synthesizer\Illustration\Director\Drivers\OpenAICompatibleDirectorDriver;
use App\Services\Synthesizer\Illustration\Illustrator\Drivers\OpenAICompatibleIllustratorDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\OpenAICompatibleOutlineBuilderDriver;
use App\Services\Synthesizer\Researcher\Drivers\OpenAICompatibleResearcherDriver;
use App\Services\Synthesizer\Researcher\Drivers\OpenAIResearcherDriver;
use App\Services\Synthesizer\Support\MaxRectificationRoundsResolver;
use App\Services\Synthesizer\Writer\Drivers\OpenAICompatibleWriterDriver;
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
        $this->assertInstanceOf(BasicResearcherDriver::class, $driver->getResearcher());
        $this->assertInstanceOf(BasicBriefBuilderDriver::class, $driver->getBriefBuilder());
        $this->assertInstanceOf(BasicOutlineBuilderDriver::class, $driver->getOutlineBuilder());
        $this->assertInstanceOf(BasicEditorDriver::class, $driver->getEditor());
        $this->assertNotEmpty($driver->getCritics());
        $this->assertContainsOnlyInstancesOf(BasicCriticDriver::class, $driver->getCritics());
        $this->assertSame(['voice', 'structure', 'clarity', 'concision', 'fingerprint', 'evidence', 'general'], array_map(
            static fn ($critic) => $critic->getPurpose(),
            $driver->getCritics()
        ));
        $this->assertInstanceOf(BasicWriterDriver::class, $driver->getWriter());
        $this->assertInstanceOf(BasicDirectorDriver::class, $driver->getIllustrationDirector());
        $this->assertNotEmpty($driver->getIllustrators());
        $this->assertInstanceOf(BasicIllustratorDriver::class, $driver->getIllustrators()[0]);

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

        $context = (new SemanticContext)->set('article_context', 'Article context', 'Latest AI coding tools pricing and adoption trends');
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
        $outlineContext = (new SemanticContext)->set(
            'outline_focus',
            'Additional outline focus.',
            'Include a section about trade-offs'
        );
        $authorContext = (new SemanticContext)->set(
            'tone',
            'Preferred writing tone.',
            'Keep tone practical and concise.'
        );
        $outline = $driver->getOutlineBuilder()->outline($brief, $outlineContext);
        $draft = $driver->getWriter()->draft($brief, $outline, $authorContext);

        $this->assertInstanceOf(Brief::class, $brief);
        $this->assertInstanceOf(Outline::class, $outline);
        $this->assertInstanceOf(Draft::class, $draft);
        $this->assertNotEmpty($brief->getTitle());
        $this->assertNotEmpty($outline->getItems());
        $this->assertStringContainsString('<h2>Introduction</h2>', (string) $draft->getArticle()?->toHtml());
    }

    public function test_it_selects_sub_service_driver_from_configuration(): void
    {
        Config::set('synthesizer.drivers.basic.brief_builder', 'openai');

        $driver = SynthesizerFacade::driver('basic');

        $this->assertInstanceOf(OpenAIBriefBuilderDriver::class, $driver->getBriefBuilder());
    }

    public function test_openai_driver_wires_openai_idea_advisor_auditor_and_picker(): void
    {
        Config::set('synthesizer.default', 'openai');

        $driver = SynthesizerFacade::driver('openai');
        $ideaForge = $driver->getIdeaForge();

        $this->assertInstanceOf(OpenAIIdeaAdvisorDriver::class, $ideaForge->getIdeaAdvisors()[0]);
        $this->assertInstanceOf(OpenAIIdeaAuditorDriver::class, $ideaForge->getIdeaAuditor());
        $this->assertInstanceOf(OpenAIIdeaPickerDriver::class, $ideaForge->getIdeaPicker());
        $this->assertInstanceOf(OpenAIResearcherDriver::class, $driver->getResearcher());
        $this->assertInstanceOf(OpenAIOutlineBuilderDriver::class, $driver->getOutlineBuilder());
        $this->assertInstanceOf(OpenAIEditorDriver::class, $driver->getEditor());
        $this->assertNotEmpty($driver->getCritics());
        $this->assertContainsOnlyInstancesOf(OpenAICriticDriver::class, $driver->getCritics());
        $this->assertInstanceOf(OpenAIWriterDriver::class, $driver->getWriter());
        $this->assertInstanceOf(OpenAIDirectorDriver::class, $driver->getIllustrationDirector());
        $this->assertNotEmpty($driver->getIllustrators());
        $this->assertInstanceOf(OpenAIIllustratorDriver::class, $driver->getIllustrators()[0]);
    }

    public function test_openai_compatible_driver_wires_compatible_subservices(): void
    {
        Config::set('synthesizer.default', 'openai_compatible');

        $driver = SynthesizerFacade::driver('openai_compatible');
        $ideaForge = $driver->getIdeaForge();

        $this->assertInstanceOf(OpenAICompatibleIdeaAdvisorDriver::class, $ideaForge->getIdeaAdvisors()[0]);
        $this->assertInstanceOf(OpenAICompatibleIdeaExpansionAdvisorDriver::class, $ideaForge->getIdeaAdvisors()[1]);
        $this->assertInstanceOf(OpenAICompatibleIdeaAuditorDriver::class, $ideaForge->getIdeaAuditor());
        $this->assertInstanceOf(OpenAICompatibleIdeaPickerDriver::class, $ideaForge->getIdeaPicker());
        $this->assertInstanceOf(OpenAICompatibleResearcherDriver::class, $driver->getResearcher());
        $this->assertInstanceOf(OpenAICompatibleBriefBuilderDriver::class, $driver->getBriefBuilder());
        $this->assertInstanceOf(OpenAICompatibleOutlineBuilderDriver::class, $driver->getOutlineBuilder());
        $this->assertInstanceOf(OpenAICompatibleEditorDriver::class, $driver->getEditor());
        $this->assertInstanceOf(OpenAICompatibleWriterDriver::class, $driver->getWriter());
        $this->assertInstanceOf(OpenAICompatibleDirectorDriver::class, $driver->getIllustrationDirector());
        $this->assertInstanceOf(OpenAICompatibleIllustratorDriver::class, $driver->getIllustrators()[0]);
    }

    public function test_driver_profiles_openai_compatible_lists_compatible_subservice_drivers(): void
    {
        $profile = \App\Services\Synthesizer\Support\SynthesizerDriverProfiles::openaiCompatible();

        $this->assertSame('openai_compatible', $profile['researcher']);
        $this->assertSame('openai_compatible', $profile['brief_builder']);
        $this->assertSame('openai_compatible', $profile['idea_forge']['auditor']);
        $this->assertSame('openai_compatible', $profile['idea_forge']['advisors'][0]['driver']);
        $this->assertSame('openai_compatible_expansion', $profile['idea_forge']['advisors'][1]['driver']);
        $this->assertSame(
            [
                \App\Services\Synthesizer\Support\CriticProfileEntry::entry('openai_compatible', 'general', 0),
                \App\Services\Synthesizer\Support\CriticProfileEntry::entry('openai_compatible', 'voice', 1),
                \App\Services\Synthesizer\Support\CriticProfileEntry::entry('openai_compatible', 'structure', 2),
                \App\Services\Synthesizer\Support\CriticProfileEntry::entry('openai_compatible', 'clarity', 3),
                \App\Services\Synthesizer\Support\CriticProfileEntry::entry('openai_compatible', 'evidence', 4),
                \App\Services\Synthesizer\Support\CriticProfileEntry::entry('openai_compatible', 'concision', 5, ['max_rectification_rounds' => 5]),
                \App\Services\Synthesizer\Support\CriticProfileEntry::entry('openai_compatible', 'fingerprint', 6, ['max_rectification_rounds' => 6]),
            ],
            $profile['critics']
        );
    }

    public function test_critic_max_rectification_rounds_uses_entry_or_global_default(): void
    {
        config()->set('synthesizer.max_rectification_rounds', 5);

        $this->assertSame(1, MaxRectificationRoundsResolver::forEntry([
            'purpose' => 'clarity',
            'max_rectification_rounds' => 1,
        ]));
        $this->assertSame(2, MaxRectificationRoundsResolver::forEntry([
            'purpose' => 'clarity',
            'max_rectification_rounds' => 2,
        ]));
        $this->assertSame(5, MaxRectificationRoundsResolver::forEntry(['purpose' => 'voice']));
        $this->assertSame(5, MaxRectificationRoundsResolver::forEntry([
            'purpose' => 'structure',
            'max_rectification_rounds' => null,
        ]));
    }

    public function test_critic_max_rectification_rounds_per_entry_is_independent(): void
    {
        config()->set('synthesizer.max_rectification_rounds', 2);

        $this->assertSame(1, MaxRectificationRoundsResolver::forEntry([
            'purpose' => 'voice',
            'max_rectification_rounds' => 1,
        ]));
        $this->assertSame(3, MaxRectificationRoundsResolver::forEntry([
            'purpose' => 'clarity',
            'max_rectification_rounds' => 3,
        ]));
    }
}
