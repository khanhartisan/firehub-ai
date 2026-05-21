<?php

namespace Tests\Unit\Services\Synthesizer\Editor;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\Model\Author\AuthorContexts\LinguisticContext;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Services\Synthesizer\Editor\Drivers\BasicEditorDriver;
use App\Services\Synthesizer\Editor\Drivers\OpenAIEditorDriver;
use Mockery;
use Tests\TestCase;

class OpenAIEditorDriverTest extends TestCase
{
    public function test_determine_author_context_without_client_falls_back_to_basic_driver(): void
    {
        $driver = new OpenAIEditorDriver;
        $idea = new Idea($this->makeIntent(), 0.8, 'Practical SaaS onboarding playbook');

        $weakMatch = (new SemanticContext)->set('voice', 'Author voice', 'macroeconomics commentary');
        $strongMatch = (new SemanticContext)->set(
            'voice',
            'Author voice',
            'Practical SaaS onboarding guidance for operators'
        );

        $picked = $driver->determineAuthorContext($idea, [$weakMatch, $strongMatch]);

        $this->assertSame(
            'Practical SaaS onboarding guidance for operators',
            $picked->getVoiceValue()
        );
    }

    public function test_determine_author_context_uses_structured_openai_response(): void
    {
        $first = (new AuthorContext)->set('voice', 'First voice', 'Formal analyst tone');
        $firstId = $first->getIdentifier();
        $second = (new AuthorContext)->set('voice', 'Second voice', 'Practical operator tone');
        $secondId = $second->getIdentifier();

        $payload = json_encode([
            'author_context_identifier' => $secondId,
            'rationale' => 'Better fit for practical onboarding content.',
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_editor_1',
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

        $driver = new OpenAIEditorDriver($client, ['model' => 'gpt-4o-mini']);
        $idea = new Idea($this->makeIntent(), 0.8, 'Practical SaaS onboarding playbook');

        $picked = $driver->determineAuthorContext($idea, [$first, $second]);

        $this->assertSame('Practical operator tone', $picked->getVoiceValue());
        $this->assertNotSame($firstId, $picked->getIdentifier());
    }

    public function test_determine_author_context_single_candidate_skips_openai(): void
    {
        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldNotReceive('createResponse');

        $driver = new OpenAIEditorDriver($client);
        $only = (new SemanticContext)->set('voice', 'Author voice', 'Only persona');
        $idea = new Idea($this->makeIntent(), 0.8, 'Test');

        $picked = $driver->determineAuthorContext($idea, [$only]);

        $this->assertSame('Only persona', $picked->getVoiceValue());
    }

    public function test_distill_author_context_for_outline_item_without_client_falls_back_to_basic_driver(): void
    {
        $driver = new OpenAIEditorDriver(null, [], new BasicEditorDriver);
        $item = (new OutlineItem)->setPoint(
            (new RelevantPoint)
                ->setHeadline('Activation tactics')
                ->setDescription('Explain onboarding experiments.')
        );
        $item->setGuidelines(['Use concrete metrics.']);
        $outline = (new Outline)->setItems([$item]);

        $authorContext = (new AuthorContext)
            ->setLinguisticContext(
                (new LinguisticContext)->setVocabularyTier('Colloquial', 2.0)
            );

        $distilled = $driver->distillAuthorContextForOutlineItem(
            $outline,
            $item->getIdentifier(),
            $authorContext,
            null
        );

        $this->assertNull($distilled->getLinguisticContextWeight());
        $this->assertSame('Activation tactics', $distilled->getSectionHeadlineValue());
    }

    public function test_distill_author_context_for_outline_item_uses_structured_openai_response(): void
    {
        $item = (new OutlineItem)->setPoint(
            (new RelevantPoint)
                ->setHeadline('Activation tactics')
                ->setDescription('Explain onboarding experiments.')
        );
        $item->setGuidelines(['Use concrete metrics.']);
        $outline = (new Outline)->setItems([$item]);

        $authorContext = (new AuthorContext)
            ->setLinguisticContext(
                (new LinguisticContext)->setVocabularyTier('Colloquial')
            )
            ->set('cognitive_context', 'Cognitive lens', ['worldview' => ['value' => 'Pragmatic']], 1.0);

        $generalContext = (new SemanticContext)->set(
            'outline_focus',
            'Additional outline focus.',
            'Emphasize trade-offs'
        );

        $payload = json_encode([
            'retained_keys' => ['linguistic_context'],
            'general_keys' => ['outline_focus'],
            'section_editorial_notes' => 'Lead with operator pain, then metrics.',
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_editor_2',
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

        $driver = new OpenAIEditorDriver($client, ['model' => 'gpt-4o-mini']);
        $distilled = $driver->distillAuthorContextForOutlineItem(
            $outline,
            $item->getIdentifier(),
            $authorContext,
            $generalContext
        );

        $this->assertSame('Colloquial', $distilled->getLinguisticContextValue()['vocabulary_tier']['value'] ?? null);
        $this->assertFalse($distilled->has('cognitive_context'));
        $this->assertSame('Emphasize trade-offs', $distilled->getGeneralOutlineFocusValue());
        $this->assertSame('Lead with operator pain, then metrics.', $distilled->getSectionEditorialNotesValue());
        $this->assertSame('Activation tactics', $distilled->getSectionHeadlineValue());
    }

    protected function makeIntent(): Intent
    {
        return (new Intent)
            ->setTitle('SaaS onboarding playbook')
            ->setDescription('How teams improve activation with practical experiments.')
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::EVERGREEN)
            ->setTypes([IntentType::INFORMATIONAL]);
    }
}
