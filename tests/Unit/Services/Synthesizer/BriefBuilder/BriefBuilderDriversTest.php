<?php

namespace Tests\Unit\Services\Synthesizer\BriefBuilder;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Enums\ContentGoal;
use App\Enums\ContentTone;
use App\Enums\ContentVoice;
use App\Enums\Country;
use App\Enums\IntentType;
use App\Enums\KnowledgeLevel;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Services\Synthesizer\BriefBuilder\Drivers\BasicBriefBuilderDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\OpenAIBriefBuilderDriver;
use Mockery;
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
        $this->assertCount(1, $brief->getInstructions());
    }

    public function test_openai_brief_builder_prefers_ai_title_and_description_over_intent(): void
    {
        $payload = json_encode([
            'temporal' => Temporal::TRENDING->value,
            'title' => 'AI-first onboarding playbook for SaaS teams',
            'description' => 'A practical brief focused on rolling out AI onboarding flows with measurable activation improvements.',
            'goal' => ContentGoal::CONVERT->value,
            'voice' => ContentVoice::AUTHORITATIVE->value,
            'tone' => ContentTone::INSTRUCTIONAL->value,
            'instructions' => [
                'Prioritize concrete rollout steps with ownership per team.',
                'Include measurable activation metrics and checkpoints.',
            ],
            'audiences' => [[
                'priority_weight' => 0.8,
                'name' => 'SaaS growth managers',
                'description' => 'Leads growth experiments and onboarding optimization.',
                'age_from' => 25,
                'age_to' => 45,
                'knowledge_level' => KnowledgeLevel::INTERMEDIATE->value,
                'language' => Language::EN->value,
                'countries' => [Country::US->value, Country::GB->value],
                'pain_points' => ['Slow activation'],
                'concerns' => ['Tool sprawl'],
                'aspirations' => ['Faster onboarding wins'],
                'fears' => ['Rolling out unmeasurable experiments'],
            ]],
            'reference_page_ids' => ['page-1', 'page-2'],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_brief_1',
            'created_at' => time(),
            'status' => 'completed',
            'model' => 'gpt-4o-mini',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => $payload,
                ]],
            ]],
        ]);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')->once()->andReturn($response);

        $driver = new OpenAIBriefBuilderDriver($client, ['model' => 'gpt-4o-mini']);
        $idea = new Idea($this->makeIntent(), 0.9, 'reason');

        $brief = $driver->conceive(
            $idea,
            (new SemanticContext)->set('article_context', 'Article context', 'context fallback')
        );

        $this->assertSame('AI-first onboarding playbook for SaaS teams', $brief->getTitle());
        $this->assertSame(
            'A practical brief focused on rolling out AI onboarding flows with measurable activation improvements.',
            $brief->getDescription()
        );
        $this->assertSame(Temporal::TRENDING, $brief->getTemporal());
        $this->assertSame(ContentGoal::CONVERT, $brief->getGoal());
        $this->assertSame(ContentVoice::AUTHORITATIVE, $brief->getVoice());
        $this->assertSame(ContentTone::INSTRUCTIONAL, $brief->getTone());
        $this->assertSame(['page-1', 'page-2'], $brief->getReferencePageIds());
        $this->assertCount(1, $brief->getAudiences());
        $this->assertSame(KnowledgeLevel::INTERMEDIATE, $brief->getAudiences()[0]->getKnowledgeLevel());
        $this->assertSame(Language::EN, $brief->getAudiences()[0]->getLanguage());
        $this->assertSame([Country::US, Country::GB], $brief->getAudiences()[0]->getCountries());
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
