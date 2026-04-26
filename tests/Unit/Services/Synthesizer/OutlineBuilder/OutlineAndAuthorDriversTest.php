<?php

namespace Tests\Unit\Services\Synthesizer\OutlineBuilder;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Services\Synthesizer\Author\Drivers\BasicAuthorDriver;
use App\Services\Synthesizer\Author\Drivers\OpenAIAuthorDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\BasicOutlineBuilderDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\OpenAIOutlineBuilderDriver;
use Mockery;
use Tests\TestCase;

class OutlineAndAuthorDriversTest extends TestCase
{
    public function test_outline_builder_creates_three_sections_and_includes_context_guidance(): void
    {
        $driver = new BasicOutlineBuilderDriver;
        $brief = (new Brief)
            ->setTitle('AI weekly')
            ->setDescription('Top developments this week.')
            ->setInstructions(['Focus on practical impact']);
        $context = (new SemanticContext)
            ->set('editorial_angle', 'Editorial angle to emphasize.', 'Add trade-offs section');

        $outline = $driver->outline($brief, $context);

        $this->assertSame('AI weekly', $outline->getTitle());
        $this->assertCount(3, $outline->getItems());
        $this->assertSame('Introduction', $outline->getItems()[0]->getPoint()->getHeadline());
        $this->assertSame('Main insights', $outline->getItems()[1]->getPoint()->getHeadline());
        $this->assertContains(
            'Use context "editorial_angle": "Add trade-offs section"',
            $outline->getItems()[1]->getGuidelines()
        );
    }

    public function test_author_driver_builds_markdown_sections_from_outline(): void
    {
        $author = new BasicAuthorDriver;
        $brief = (new Brief)
            ->setTitle('AI weekly')
            ->setDescription('Top developments this week.');

        $outline = (new \App\Contracts\Synthesizer\OutlineBuilder\Outline)
            ->setItems([
                (new OutlineItem)->setPoint(
                    (new RelevantPoint)
                        ->setHeadline('Intro')
                        ->setDescription('Opening')
                        ->setEvidences(['Keep concise'])
                ),
                (new OutlineItem)->setPoint(
                    (new RelevantPoint)
                        ->setHeadline('Body')
                        ->setDescription('Details')
                        ->setEvidences(['Use bullets'])
                ),
            ]);
        $context = (new SemanticContext)
            ->set('tone', 'Preferred writing tone.', 'Be practical');

        $draft = $author->draft($brief, $outline, $context);
        $articleHtml = (string) $draft->getArticle()?->toHtml();

        $this->assertSame('AI weekly', $draft->getTitle());
        $this->assertStringContainsString('<h2>Intro</h2>', $articleHtml);
        $this->assertStringContainsString('<h2>Body</h2>', $articleHtml);
        $this->assertStringContainsString('<h2>Additional context</h2>', $articleHtml);
        $this->assertStringContainsString('Use context &quot;tone&quot;: &quot;Be practical&quot;', $articleHtml);
    }

    public function test_openai_author_driver_hydrates_article_from_structured_response(): void
    {
        $payload = json_encode([
            'title' => 'AI weekly',
            'excerpt' => 'Top developments this week.',
            'article' => [
                'type' => 'article',
                'props' => [],
                'children' => [
                    [
                        'type' => 'h2',
                        'props' => [],
                        'children' => ['Intro'],
                    ],
                    [
                        'type' => 'p',
                        'props' => [],
                        'children' => ['Opening paragraph'],
                    ],
                    [
                        'type' => 'ul',
                        'props' => [],
                        'children' => [
                            [
                                'type' => 'li',
                                'props' => [],
                                'children' => ['First signal'],
                            ],
                            [
                                'type' => 'li',
                                'props' => [],
                                'children' => ['Second signal'],
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_author_1',
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

        $author = new OpenAIAuthorDriver($client, ['model' => 'gpt-4o-mini']);
        $brief = (new Brief)
            ->setTitle('Fallback title')
            ->setDescription('Fallback excerpt');
        $outline = (new \App\Contracts\Synthesizer\OutlineBuilder\Outline)
            ->setItems([
                (new OutlineItem)->setPoint(
                    (new RelevantPoint)
                        ->setHeadline('Intro')
                        ->setDescription('Opening')
                ),
            ]);

        $draft = $author->draft($brief, $outline);

        $this->assertSame('AI weekly', $draft->getTitle());
        $this->assertSame('Top developments this week.', $draft->getExcerpt());
        $this->assertNotNull($draft->getArticle());
        $this->assertStringContainsString('<h2>Intro</h2>', $draft->getArticle()->toHtml());
        $this->assertStringContainsString('<p>Opening paragraph</p>', $draft->getArticle()->toHtml());
        $this->assertStringContainsString('<li>First signal</li>', $draft->getArticle()->toHtml());
    }

    public function test_openai_outline_builder_hydrates_outline_from_structured_response(): void
    {
        $payload = json_encode([
            'title' => 'AI weekly deep dive',
            'items' => [
                [
                    'point' => [
                        'headline' => 'Market snapshot',
                        'description' => 'Summarize major releases this week.',
                        'evidences' => ['Mention launch dates', 'Compare strategic positioning'],
                        'relevance' => 0.9,
                        'rationale' => 'High value context for the audience.',
                    ],
                    'guidelines' => ['Keep sections concise', 'Lead with practical implications'],
                    'sub_points' => [
                        [
                            'headline' => 'Product launches',
                            'description' => 'Highlight key launches.',
                            'evidences' => ['Use bullet points'],
                            'relevance' => 0.8,
                            'rationale' => 'Directly supports the section objective.',
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_outline_1',
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

        $driver = new OpenAIOutlineBuilderDriver($client, ['model' => 'gpt-4o-mini']);
        $brief = (new Brief)
            ->setTitle('AI weekly')
            ->setDescription('Top developments this week.')
            ->setInstructions(['Focus on practical impact']);

        $context = (new SemanticContext)
            ->set('editorial_angle', 'Editorial angle to emphasize.', 'Add trade-offs section');
        $outline = $driver->outline($brief, $context);

        $this->assertSame('AI weekly deep dive', $outline->getTitle());
        $this->assertCount(1, $outline->getItems());
        $this->assertSame('Market snapshot', $outline->getItems()[0]->getPoint()->getHeadline());
        $this->assertContains('Keep sections concise', $outline->getItems()[0]->getGuidelines());
        $this->assertCount(1, $outline->getItems()[0]->getSubPoints());
        $this->assertSame('Product launches', $outline->getItems()[0]->getSubPoints()[0]->getHeadline());
    }

    public function test_openai_outline_builder_throws_when_client_not_configured(): void
    {
        $driver = new OpenAIOutlineBuilderDriver;
        $brief = (new Brief)
            ->setTitle('AI weekly')
            ->setDescription('Top developments this week.')
            ->setInstructions(['Focus on practical impact']);

        $context = (new SemanticContext)
            ->set('editorial_angle', 'Editorial angle to emphasize.', 'Add trade-offs section');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenAI client is not configured');

        $driver->outline($brief, $context);
    }
}
