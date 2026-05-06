<?php

namespace Tests\Unit\Services\Synthesizer\OutlineBuilder;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\DOM\Article;
use App\Contracts\Synthesizer\Writer\IllustrationAnchor;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Services\Synthesizer\Writer\Drivers\BasicWriterDriver;
use App\Services\Synthesizer\Writer\Drivers\OpenAIWriterDriver;
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
        $author = new BasicWriterDriver;
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

    public function test_basic_writer_driver_maps_illustration_results_to_dom_anchors(): void
    {
        $author = new BasicWriterDriver;
        $brief = (new Brief)
            ->setTitle('AI weekly')
            ->setDescription('Top developments this week.');
        $outline = (new \App\Contracts\Synthesizer\OutlineBuilder\Outline)
            ->setItems([
                (new OutlineItem)->setPoint(
                    (new RelevantPoint)
                        ->setHeadline('Intro')
                        ->setDescription('Opening')
                ),
                (new OutlineItem)->setPoint(
                    (new RelevantPoint)
                        ->setHeadline('Body')
                        ->setDescription('Details')
                ),
            ]);

        $draft = $author->draft($brief, $outline, null);
        $article = $draft->getArticle();
        $this->assertNotNull($article);

        $first = new IllustrationResult;
        $second = new IllustrationResult;

        $anchors = $author->getIllustrationAnchors($article, [$first, $second]);

        $this->assertCount(2, $anchors);
        $this->assertContainsOnlyInstancesOf(IllustrationAnchor::class, $anchors);
        $this->assertSame($first->getIdentifier(), $anchors[0]->getIllustrationIdentifier());
        $this->assertSame($second->getIdentifier(), $anchors[1]->getIllustrationIdentifier());
        $this->assertNotSame($anchors[0]->getElementIdentifier(), $anchors[1]->getElementIdentifier());
        $this->assertTrue($anchors[0]->isAfter());
        $this->assertTrue($anchors[1]->isAfter());
    }

    public function test_openai_writer_driver_resolves_illustration_anchors_via_structured_response(): void
    {
        $article = Article::fromArray([
            'identifier' => 'article-root',
            'type' => 'article',
            'props' => [],
            'children' => [
                [
                    'identifier' => 'el-heading',
                    'type' => 'h2',
                    'props' => [],
                    'children' => ['Section'],
                ],
                [
                    'identifier' => 'el-body',
                    'type' => 'p',
                    'props' => [],
                    'children' => ['Body text'],
                ],
            ],
        ]);

        $first = new IllustrationResult;
        $second = new IllustrationResult;
        $id1 = $first->getIdentifier();
        $id2 = $second->getIdentifier();

        $payload = json_encode([
            'anchors' => [
                [
                    'illustration_identifier' => $id1,
                    'element_identifier' => 'el-heading',
                    'is_after' => true,
                ],
                [
                    'illustration_identifier' => $id2,
                    'element_identifier' => 'el-body',
                    'is_after' => false,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_author_anchors_1',
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

        $author = new OpenAIWriterDriver($client, ['model' => 'gpt-4o-mini']);
        $anchors = $author->getIllustrationAnchors($article, [$first, $second]);

        $this->assertCount(2, $anchors);
        $this->assertSame($id1, $anchors[0]->getIllustrationIdentifier());
        $this->assertSame('el-heading', $anchors[0]->getElementIdentifier());
        $this->assertTrue($anchors[0]->isAfter());
        $this->assertSame($id2, $anchors[1]->getIllustrationIdentifier());
        $this->assertSame('el-body', $anchors[1]->getElementIdentifier());
        $this->assertFalse($anchors[1]->isAfter());
    }

    public function test_openai_writer_driver_throws_when_client_missing_for_illustration_anchors(): void
    {
        $author = new OpenAIWriterDriver;
        $article = Article::fromArray([
            'identifier' => 'article-root',
            'type' => 'article',
            'props' => [],
            'children' => [
                [
                    'identifier' => 'el-heading',
                    'type' => 'h2',
                    'props' => [],
                    'children' => ['Section'],
                ],
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenAI author driver requires an OpenAI client instance');

        $author->getIllustrationAnchors($article, [new IllustrationResult]);
    }

    public function test_openai_writer_driver_hydrates_article_from_structured_response(): void
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

        $author = new OpenAIWriterDriver($client, ['model' => 'gpt-4o-mini']);
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
                    'sub_items' => [
                        [
                            'point' => [
                                'headline' => 'Product launches',
                                'description' => 'Highlight key launches.',
                                'evidences' => ['Use bullet points'],
                                'relevance' => 0.8,
                                'rationale' => 'Directly supports the section objective.',
                            ],
                            'guidelines' => ['Group launches by audience impact'],
                            'sub_items' => [],
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
        $this->assertCount(1, $outline->getItems()[0]->getSubItems());
        $this->assertSame('Product launches', $outline->getItems()[0]->getSubItems()[0]->getPoint()->getHeadline());
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
