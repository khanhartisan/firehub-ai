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
use App\Services\Synthesizer\Editor\Drivers\OpenAIEditorDriver;
use RuntimeException;
use Mockery;
use Tests\TestCase;

class OpenAIEditorDriverTest extends TestCase
{
    public function test_determine_author_context_without_client_throws(): void
    {
        $driver = new OpenAIEditorDriver;
        $idea = new Idea($this->makeIntent(), 0.8, 'Practical SaaS onboarding playbook');

        $weakMatch = (new SemanticContext)->set('voice', 'Author voice', 'macroeconomics commentary');
        $strongMatch = (new SemanticContext)->set(
            'voice',
            'Author voice',
            'Practical SaaS onboarding guidance for operators'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI client is not configured');

        $driver->determineAuthorContext($idea, [$weakMatch, $strongMatch]);
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

    public function test_tailor_outline_for_author_without_client_throws(): void
    {
        $driver = new OpenAIEditorDriver;
        $item = (new OutlineItem)->setPoint(
            (new RelevantPoint)->setHeadline('Activation tactics')->setDescription('Body')
        );
        $outline = (new Outline)->setItems([$item]);
        $authorContext = (new SemanticContext)->set('voice', 'Author voice', 'Operator-first tone');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI client is not configured');

        $driver->tailorOutlineForAuthor($outline, $authorContext);
    }

    public function test_tailor_outline_for_author_uses_structured_openai_response(): void
    {
        $outline = (new Outline)
            ->setTitle('Generic draft')
            ->setItems([
                (new OutlineItem)->setPoint(
                    (new RelevantPoint)
                        ->setHeadline('Introduction')
                        ->setDescription('Set context.')
                ),
            ]);

        $authorContext = (new AuthorContext)->set(
            'voice',
            'Author voice',
            'Practical operator tone'
        );

        $payload = json_encode([
            'title' => 'Operator playbook outline',
            'items' => [[
                'point' => [
                    'headline' => 'Operator-first opening',
                    'description' => 'Lead with pain, then metrics.',
                    'evidences' => [],
                    'relevance' => null,
                    'rationale' => null,
                ],
                'guidelines' => ['Use concrete SaaS examples.'],
                'sub_items' => [],
            ]],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_editor_3',
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
        $tailored = $driver->tailorOutlineForAuthor($outline, $authorContext);

        $this->assertSame('Operator playbook outline', $tailored->getTitle());
        $this->assertSame('Operator-first opening', $tailored->getItems()[0]->getPoint()->getHeadline());
        $this->assertSame(['Use concrete SaaS examples.'], $tailored->getItems()[0]->getGuidelines());
    }

    public function test_distill_author_context_for_outline_item_without_client_throws(): void
    {
        $driver = new OpenAIEditorDriver;
        $item = (new OutlineItem)->setPoint(
            (new RelevantPoint)
                ->setHeadline('Activation tactics')
                ->setDescription('Explain onboarding experiments.')
        );
        $outline = (new Outline)->setItems([$item]);
        $authorContext = (new AuthorContext)
            ->setLinguisticContext(
                (new LinguisticContext)->setVocabularyTier('Colloquial', 2.0)
            );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI client is not configured');

        $driver->distillAuthorContextForOutlineItem(
            $outline,
            $item->getIdentifier(),
            $authorContext,
            null
        );
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
