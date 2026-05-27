<?php

namespace Tests\Unit\Services\Synthesizer\OutlineBuilder;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\DOM\Article;
use App\Contracts\Synthesizer\Critic\Criticism;
use App\Contracts\Synthesizer\Writer\IllustrationAnchor;
use App\Contracts\Synthesizer\Critic\Rectification;
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

    public function test_basic_writer_driver_rectifies_article_from_criticisms(): void
    {
        $author = new BasicWriterDriver;
        $article = Article::fromArray([
            'identifier' => 'root',
            'type' => 'article',
            'props' => [],
            'children' => [
                [
                    'identifier' => 'thin',
                    'type' => 'p',
                    'props' => [],
                    'children' => ['Too short.'],
                ],
            ],
        ]);

        $result = $author->rectifyArticle($article, [
            (new Criticism)
                ->setReference('thin')
                ->setPurpose('clarity')
                ->setRemarks(['Section is too thin; expand with supporting detail.']),
        ]);

        $this->assertNotNull($result->getArticle());
        $this->assertStringContainsString(
            'Section is too thin; expand with supporting detail.',
            $result->getArticle()->toHtml()
        );
        $this->assertCount(1, $result->getRectifications());
        $this->assertInstanceOf(Rectification::class, $result->getRectifications()[0]);
        $this->assertSame('thin', $result->getRectifications()[0]->getReference());
    }

    public function test_openai_writer_driver_rectifies_referenced_criticisms_with_targeted_dom_fixes(): void
    {
        $payload = json_encode([
            'fixes' => [
                [
                    'reference' => 'thin',
                    'operation' => 'replace',
                    'elements' => [
                        [
                            'identifier' => 'thin',
                            'type' => 'p',
                            'props' => (object) [],
                            'children' => ['Expanded opening with more supporting detail.'],
                        ],
                    ],
                ],
            ],
            'rectifications' => [
                [
                    'reference' => 'thin',
                    'confidence' => 0.92,
                    'adjustments' => ['Expanded the thin section with supporting detail.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_author_rectify_targeted_1',
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
        $article = Article::fromArray([
            'identifier' => 'root',
            'type' => 'article',
            'props' => [],
            'children' => [
                [
                    'identifier' => 'keep',
                    'type' => 'p',
                    'props' => [],
                    'children' => ['Leave unchanged.'],
                ],
                [
                    'identifier' => 'thin',
                    'type' => 'p',
                    'props' => [],
                    'children' => ['Too short.'],
                ],
            ],
        ]);

        $result = $author->rectifyArticle($article, [
            (new Criticism)
                ->setReference('thin')
                ->setPurpose('clarity')
                ->setRemarks(['Section is too thin; expand with supporting detail.']),
        ]);

        $this->assertNotNull($result->getArticle());
        $this->assertStringContainsString('Leave unchanged.', $result->getArticle()->toHtml());
        $this->assertStringContainsString('Expanded opening with more supporting detail.', $result->getArticle()->toHtml());
        $this->assertStringNotContainsString('Too short.', $result->getArticle()->toHtml());
        $this->assertCount(1, $result->getRectifications());
        $this->assertSame('thin', $result->getRectifications()[0]->getReference());
        $this->assertSame(0.92, $result->getRectifications()[0]->getConfidence());
        $this->assertSame(
            ['Expanded the thin section with supporting detail.'],
            $result->getRectifications()[0]->getAdjustments()
        );
    }

    public function test_openai_writer_driver_can_remove_referenced_node_when_targeted_fix_marks_removed(): void
    {
        $payload = json_encode([
            'fixes' => [
                [
                    'reference' => 'thin',
                    'operation' => 'remove',
                ],
            ],
            'rectifications' => [
                [
                    'reference' => 'thin',
                    'confidence' => 0.9,
                    'adjustments' => ['Removed redundant thin section.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_author_rectify_remove_1',
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
        $article = Article::fromArray([
            'identifier' => 'root',
            'type' => 'article',
            'props' => [],
            'children' => [
                [
                    'identifier' => 'keep',
                    'type' => 'p',
                    'props' => [],
                    'children' => ['Leave unchanged.'],
                ],
                [
                    'identifier' => 'thin',
                    'type' => 'p',
                    'props' => [],
                    'children' => ['Too short.'],
                ],
            ],
        ]);

        $result = $author->rectifyArticle($article, [
            (new Criticism)
                ->setReference('thin')
                ->setRemarks(['Remove this redundant section entirely.']),
        ]);

        $html = $result->getArticle()->toHtml();
        $this->assertStringContainsString('Leave unchanged.', $html);
        $this->assertStringNotContainsString('Too short.', $html);
        $this->assertCount(1, $result->getRectifications());
        $this->assertSame('thin', $result->getRectifications()[0]->getReference());
    }

    public function test_basic_writer_driver_can_remove_node_when_criticism_requests_removal(): void
    {
        $author = new BasicWriterDriver;
        $article = Article::fromArray([
            'identifier' => 'root',
            'type' => 'article',
            'props' => [],
            'children' => [
                [
                    'identifier' => 'keep',
                    'type' => 'p',
                    'props' => [],
                    'children' => ['Stay.'],
                ],
                [
                    'identifier' => 'drop',
                    'type' => 'p',
                    'props' => [],
                    'children' => ['Delete me.'],
                ],
            ],
        ]);

        $result = $author->rectifyArticle($article, [
            (new Criticism)
                ->setReference('drop')
                ->setRemarks(['Remove this paragraph entirely.']),
        ]);

        $html = $result->getArticle()->toHtml();
        $this->assertStringContainsString('Stay.', $html);
        $this->assertStringNotContainsString('Delete me.', $html);
        $this->assertCount(1, $result->getRectifications());
        $this->assertSame('drop', $result->getRectifications()[0]->getReference());
    }

    public function test_openai_writer_driver_can_replace_node_with_multiple_siblings(): void
    {
        $payload = json_encode([
            'fixes' => [
                [
                    'reference' => 'bloc',
                    'operation' => 'replace',
                    'elements' => [
                        [
                            'identifier' => 'bloc',
                            'type' => 'p',
                            'props' => (object) [],
                            'children' => ['First split paragraph.'],
                        ],
                        [
                            'identifier' => 'blc2',
                            'type' => 'p',
                            'props' => (object) [],
                            'children' => ['Second split paragraph.'],
                        ],
                    ],
                ],
            ],
            'rectifications' => [
                [
                    'reference' => 'bloc',
                    'confidence' => 0.9,
                    'adjustments' => ['Split the block into two paragraphs.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')->once()->andReturn($this->makeOpenAiWriterResponse($payload));

        $author = new OpenAIWriterDriver($client, ['model' => 'gpt-4o-mini']);
        $article = Article::fromArray([
            'identifier' => 'root',
            'type' => 'article',
            'props' => [],
            'children' => [
                [
                    'identifier' => 'bloc',
                    'type' => 'p',
                    'props' => [],
                    'children' => ['One dense block.'],
                ],
            ],
        ]);

        $result = $author->rectifyArticle($article, [
            (new Criticism)
                ->setReference('bloc')
                ->setRemarks(['Split this into two shorter paragraphs.']),
        ]);

        $html = $result->getArticle()->toHtml();
        $this->assertStringContainsString('First split paragraph.', $html);
        $this->assertStringContainsString('Second split paragraph.', $html);
        $this->assertStringNotContainsString('One dense block.', $html);
    }

    public function test_openai_writer_driver_can_insert_elements_after_reference(): void
    {
        $payload = json_encode([
            'fixes' => [
                [
                    'reference' => 'intr',
                    'operation' => 'insert_after',
                    'elements' => [
                        [
                            'identifier' => 'exam',
                            'type' => 'p',
                            'props' => (object) [],
                            'children' => ['Concrete example paragraph.'],
                        ],
                    ],
                ],
            ],
            'rectifications' => [
                [
                    'reference' => 'intr',
                    'confidence' => 0.88,
                    'adjustments' => ['Added an example after the intro.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')->once()->andReturn($this->makeOpenAiWriterResponse($payload));

        $author = new OpenAIWriterDriver($client, ['model' => 'gpt-4o-mini']);
        $article = Article::fromArray([
            'identifier' => 'root',
            'type' => 'article',
            'props' => [],
            'children' => [
                [
                    'identifier' => 'intr',
                    'type' => 'p',
                    'props' => [],
                    'children' => ['Intro stays.'],
                ],
            ],
        ]);

        $result = $author->rectifyArticle($article, [
            (new Criticism)
                ->setReference('intr')
                ->setRemarks(['Add a concrete example after the intro.']),
        ]);

        $html = $result->getArticle()->toHtml();
        $this->assertStringContainsString('Intro stays.', $html);
        $this->assertStringContainsString('Concrete example paragraph.', $html);
        $this->assertTrue(
            str_contains($html, 'Intro stays.') && str_contains($html, 'Concrete example paragraph.')
                && strpos($html, 'Intro stays.') < strpos($html, 'Concrete example paragraph.')
        );
    }

    public function test_openai_writer_driver_can_insert_elements_before_reference(): void
    {
        $payload = json_encode([
            'fixes' => [
                [
                    'reference' => 'body',
                    'operation' => 'insert_before',
                    'elements' => [
                        [
                            'identifier' => 'lead-in',
                            'type' => 'p',
                            'props' => (object) [],
                            'children' => ['Lead-in paragraph.'],
                        ],
                    ],
                ],
            ],
            'rectifications' => [
                [
                    'reference' => 'body',
                    'confidence' => 0.86,
                    'adjustments' => ['Added a lead-in before the body.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')->once()->andReturn($this->makeOpenAiWriterResponse($payload));

        $author = new OpenAIWriterDriver($client, ['model' => 'gpt-4o-mini']);
        $article = Article::fromArray([
            'identifier' => 'root',
            'type' => 'article',
            'props' => [],
            'children' => [
                [
                    'identifier' => 'body',
                    'type' => 'p',
                    'props' => [],
                    'children' => ['Body stays.'],
                ],
            ],
        ]);

        $result = $author->rectifyArticle($article, [
            (new Criticism)
                ->setReference('body')
                ->setRemarks(['Add a lead-in before the body section.']),
        ]);

        $html = $result->getArticle()->toHtml();
        $this->assertStringContainsString('Lead-in paragraph.', $html);
        $this->assertStringContainsString('Body stays.', $html);
        $this->assertTrue(
            str_contains($html, 'Lead-in paragraph.') && str_contains($html, 'Body stays.')
                && strpos($html, 'Lead-in paragraph.') < strpos($html, 'Body stays.')
        );
    }

    public function test_openai_writer_driver_rectifies_mixed_criticisms_with_full_article_markdown(): void
    {
        $payload = json_encode([
            'markdown' => <<<'MD'
## Intro

Expanded opening with more supporting detail.
MD,
            'rectifications' => [
                [
                    'reference' => null,
                    'confidence' => 0.85,
                    'adjustments' => ['Improved overall article flow and depth.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_author_rectify_full_1',
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
        $article = Article::fromArray([
            'identifier' => 'root',
            'type' => 'article',
            'props' => [],
            'children' => [
                [
                    'identifier' => 'thin',
                    'type' => 'p',
                    'props' => [],
                    'children' => ['Too short.'],
                ],
            ],
        ]);

        $result = $author->rectifyArticle($article, [
            (new Criticism)
                ->setReference('thin')
                ->setPurpose('clarity')
                ->setRemarks(['Section is too thin; expand with supporting detail.']),
            (new Criticism)
                ->setPurpose('voice')
                ->setRemarks(['Overall tone needs more personality.']),
        ]);

        $this->assertNotNull($result->getArticle());
        $this->assertStringContainsString('<h2>Intro</h2>', $result->getArticle()->toHtml());
        $this->assertStringContainsString('Expanded opening', $result->getArticle()->toHtml());
        $this->assertCount(1, $result->getRectifications());
        $this->assertNull($result->getRectifications()[0]->getReference());
        $this->assertSame(0.85, $result->getRectifications()[0]->getConfidence());
        $this->assertSame(
            ['Improved overall article flow and depth.'],
            $result->getRectifications()[0]->getAdjustments()
        );
    }

    public function test_basic_writer_driver_sets_rectification_confidence_from_criticisms(): void
    {
        $author = new BasicWriterDriver;
        $article = Article::fromArray([
            'identifier' => 'root',
            'type' => 'article',
            'props' => [],
            'children' => [
                [
                    'identifier' => 'thin',
                    'type' => 'p',
                    'props' => [],
                    'children' => ['Too short.'],
                ],
            ],
        ]);

        $result = $author->rectifyArticle($article, [
            (new Criticism)
                ->setReference('thin')
                ->setConfidence(0.72)
                ->setRemarks(['Expand this section.']),
            (new Criticism)
                ->setReference('thin')
                ->setConfidence(0.91)
                ->setRemarks(['Add concrete examples.']),
        ]);

        $this->assertCount(1, $result->getRectifications());
        $this->assertSame(0.91, $result->getRectifications()[0]->getConfidence());
    }

    public function test_openai_writer_driver_throws_when_client_missing_for_rectify_article(): void
    {
        $author = new OpenAIWriterDriver;
        $article = Article::fromArray([
            'identifier' => 'root',
            'type' => 'article',
            'props' => [],
            'children' => [
                [
                    'identifier' => 'thin',
                    'type' => 'p',
                    'props' => [],
                    'children' => ['Too short.'],
                ],
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenAI author driver requires an OpenAI client instance');

        $author->rectifyArticle($article, [
            (new Criticism)
                ->setReference('thin')
                ->setRemarks(['Expand this section.']),
        ]);
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
            'identifier' => 'root',
            'type' => 'article',
            'props' => [],
            'children' => [
                [
                    'identifier' => 'head',
                    'type' => 'h2',
                    'props' => [],
                    'children' => ['Section'],
                ],
                [
                    'identifier' => 'body',
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
                    'element_identifier' => 'head',
                    'is_after' => true,
                ],
                [
                    'illustration_identifier' => $id2,
                    'element_identifier' => 'body',
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
        $this->assertSame('head', $anchors[0]->getElementIdentifier());
        $this->assertTrue($anchors[0]->isAfter());
        $this->assertSame($id2, $anchors[1]->getIllustrationIdentifier());
        $this->assertSame('body', $anchors[1]->getElementIdentifier());
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

    public function test_openai_writer_driver_hydrates_article_from_markdown_response(): void
    {
        $payload = json_encode([
            'title' => 'AI weekly',
            'excerpt' => 'Top developments this week.',
            'markdown' => <<<'MD'
## Intro

Opening paragraph

- First signal
- Second signal
MD,
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

    protected function makeOpenAiWriterResponse(string $payload): Response
    {
        return Response::fromArray([
            'id' => 'resp_author_rectify_'.md5($payload),
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
}
