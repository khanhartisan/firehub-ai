<?php

namespace Tests\Unit\Services\Synthesizer\BriefBuilder;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Services\Synthesizer\BriefBuilder\Drivers\BasicBriefBuilderDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\OpenAIBriefBuilderDriver;
use Tests\TestCase;

class BriefBuilderDriversTest extends TestCase
{
    public function test_basic_brief_builder_maps_idea_fields_into_brief(): void
    {
        $driver = new BasicBriefBuilderDriver;
        $idea = new Idea($this->makeIntent(), 0.8, 'Use practical examples.');

        $brief = $driver->conceive(
            $idea,
            (new SemanticContext)->set('article_context', 'Article context', 'fallback context')
        );

        $this->assertSame(Temporal::EVERGREEN, $brief->getTemporal());
        $this->assertSame('AI productivity playbook', $brief->getTitle());
        $this->assertSame('How teams adopt AI tools.', $brief->getDescription());
        $this->assertContains('Use practical examples.', $brief->getInstructions());
        $this->assertContains('Keep claims grounded in source context.', $brief->getInstructions());
    }

    public function test_openai_brief_builder_uses_context_fallback_when_description_empty(): void
    {
        $driver = new OpenAIBriefBuilderDriver;
        $idea = new Idea($this->makeIntent(description: ''), 0.6, null);

        $brief = $driver->conceive(
            $idea,
            (new SemanticContext)->set('article_context', 'Article context', 'context fallback')
        );

        $this->assertSame('context fallback', $brief->getDescription());
        $this->assertCount(2, $brief->getInstructions());
    }

    protected function makeIntent(string $description = 'How teams adopt AI tools.'): Intent
    {
        return (new Intent)
            ->setTitle('AI productivity playbook')
            ->setDescription($description)
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::EVERGREEN)
            ->setTypes([IntentType::INFORMATIONAL]);
    }
}
