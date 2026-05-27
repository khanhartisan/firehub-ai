<?php

namespace Tests\Unit\Services\Synthesizer\Critic;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\Synthesizer\Critic\Rectification;
use App\Services\Synthesizer\Critic\ArticleCritics\EvidenceArticleCritic;
use App\Services\Synthesizer\Critic\ArticleCritics\FingerprintArticleCritic;
use App\Services\Synthesizer\Critic\ArticleCritics\VoiceArticleCritic;
use App\Services\Synthesizer\Critic\CriticManager;
use App\Services\Synthesizer\Critic\Drivers\OpenAICriticDriver;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OpenAICriticDriverTest extends TestCase
{
    public function test_criticize_article_without_client_throws(): void
    {
        $article = $this->makeArticleWithSection('sect', str_repeat('Enough words in this section body. ', 10));

        $driver = $this->openAiDriver('clarity');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI client is not configured');

        $driver->criticizeArticle($article);
    }

    public function test_criticize_article_returns_empty_when_article_has_no_identifiers(): void
    {
        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldNotReceive('createResponse');

        $driver = $this->openAiDriver('clarity', $client);
        $article = new Article;

        $this->assertSame([], $driver->criticizeArticle($article));
    }

    public function test_criticize_article_uses_structured_openai_response(): void
    {
        $article = $this->makeArticleWithSection('thin', 'Short body.');

        $payload = json_encode([
            'criticisms' => [
                [
                    'reference' => 'thin',
                    'confidence' => 0.88,
                    'importance' => 0.75,
                    'remarks' => ['Expand this section with concrete examples.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')->once()->andReturn($this->makeResponse($payload));

        $driver = $this->openAiDriver('clarity', $client, ['model' => 'gpt-4o-mini']);
        $criticisms = $driver->criticizeArticle($article);

        $this->assertCount(1, $criticisms);
        $this->assertSame('clarity', $criticisms[0]->getPurpose());
        $this->assertSame('thin', $criticisms[0]->getReference());
        $this->assertSame(0.88, $criticisms[0]->getConfidence());
        $this->assertSame(0.75, $criticisms[0]->getImportance());
    }

    public function test_criticize_article_skips_rectified_references(): void
    {
        $article = $this->makeArticleWithSection('rect', 'Short body.');

        $payload = json_encode([
            'criticisms' => [
                [
                    'reference' => 'rect',
                    'confidence' => 0.9,
                    'importance' => 0.8,
                    'remarks' => ['Should be ignored because already rectified.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')->once()->andReturn($this->makeResponse($payload));

        $driver = $this->openAiDriver('clarity', $client);
        $criticisms = $driver->criticizeArticle(
            $article,
            null,
            null,
            [(new Rectification)->setReference('rect')],
        );

        $this->assertSame([], $criticisms);
    }

    public function test_criticize_article_includes_author_context_in_prompt_payload(): void
    {
        $article = $this->makeArticleWithSection('body', str_repeat('Substantive section content here. ', 8));

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')
            ->once()
            ->withArgs(function ($input): bool {
                if (! $input instanceof \App\Contracts\OpenAI\ResponseInput) {
                    return false;
                }

                $text = $input->toArray()[0]['content'][0]['text'] ?? '';

                return str_contains($text, 'Practical founder tone')
                    && str_contains($text, '"author_context"')
                    && str_contains($text, 'author voice and tone');
            })
            ->andReturn($this->makeResponse(json_encode(['criticisms' => []], JSON_THROW_ON_ERROR)));

        $authorContext = (new SemanticContext)->set('voice', 'Author voice', 'Practical founder tone');

        $driver = $this->openAiDriver('voice', $client);
        $criticisms = $driver->criticizeArticle($article, $authorContext);

        $this->assertSame([], $criticisms);
        $this->assertSame('voice', $driver->getPurpose());
    }

    public function test_voice_article_critic_declares_voice_focused_prompt(): void
    {
        $critic = new VoiceArticleCritic;
        $prompt = (new \ReflectionMethod($critic, 'buildPrompt'))
            ->invoke($critic, ['sections' => []]);

        $this->assertSame('voice', $critic->getPurpose());
        $this->assertStringContainsString('author voice and tone', $prompt);
        $this->assertStringContainsString('Ignore structure', $prompt);
    }

    public function test_fingerprint_article_critic_declares_fingerprint_focused_prompt(): void
    {
        $critic = new FingerprintArticleCritic;
        $prompt = (new \ReflectionMethod($critic, 'buildPrompt'))
            ->invoke($critic, ['sections' => []]);

        $this->assertSame('fingerprint', $critic->getPurpose());
        $this->assertStringContainsString('AI-generated content fingerprints', $prompt);
    }

    public function test_evidence_article_critic_declares_evidence_focused_prompt(): void
    {
        $critic = new EvidenceArticleCritic;
        $prompt = (new \ReflectionMethod($critic, 'buildPrompt'))
            ->invoke($critic, ['sections' => []]);

        $this->assertSame('evidence', $critic->getPurpose());
        $this->assertStringContainsString('lack necessary evidence, details, or examples', $prompt);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function openAiDriver(string $purpose, ?OpenAIClient $client = null, array $config = []): OpenAICriticDriver
    {
        return new OpenAICriticDriver($this->app->make(CriticManager::class), $purpose, $client, $config);
    }

    protected function makeResponse(string $payload): Response
    {
        return Response::fromArray([
            'id' => 'resp_critic_1',
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
    }

    protected function makeArticleWithSection(string $reference, string $body): Article
    {
        $article = new Article;
        $article->addChild(
            (new Element)
                ->setType(ElementType::DIV)
                ->setIdentifier($reference)
                ->addChild(
                    (new Element)
                        ->setType(ElementType::P)
                        ->addChild($body)
                )
        );

        return $article;
    }
}
