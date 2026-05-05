<?php

namespace Tests\Feature\Jobs\BuildArticleJob;

use App\Contracts\DOM\Article as DOMArticle;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Contracts\Model\Article\Context as ArticleContext;
use App\Contracts\Model\Article\StageData;
use App\Contracts\Model\Client\Context as ClientContext;
use App\Contracts\Synthesizer\Author\Draft;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use App\Facades\IntentResolver;
use App\Facades\Synthesizer;
use App\Jobs\BuildArticleJob;
use App\Models\Article;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuildArticleJobIllustrationStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        config()->set('synthesizer.default', 'basic');
        Synthesizer::clearResolvedInstance('synthesizer.manager');
        app()->forgetInstance('synthesizer.manager');
        app()->forgetInstance(\App\Contracts\Synthesizer\Synthesizer::class);
    }

    // -------------------------------------------------------------------------
    // Guard rails (early exits)
    // -------------------------------------------------------------------------

    public function test_illustration_stage_returns_false_when_draft_is_missing(): void
    {
        [$article, $job] = $this->makeArticleAndJobAtIllustrationStage(null);

        $this->assertFalse($job->runIllustrationStage());
    }

    public function test_illustration_stage_returns_true_when_draft_has_no_dom(): void
    {
        $draft = (new Draft)->setTitle('T')->setExcerpt('E');
        [$article, $job] = $this->makeArticleAndJobAtIllustrationStage($draft);

        $this->assertTrue($job->runIllustrationStage());
    }

    public function test_illustration_stage_returns_false_when_dom_yields_no_contexts(): void
    {
        // Empty DOM → no markdown content → director resolves zero contexts.
        $draft = (new Draft)->setTitle('T')->setExcerpt('E')->setArticle(new DOMArticle);
        [$article, $job] = $this->makeArticleAndJobAtIllustrationStage($draft);

        $this->assertFalse($job->runIllustrationStage());
    }

    // -------------------------------------------------------------------------
    // Checkpoint behaviour
    // -------------------------------------------------------------------------

    public function test_first_run_checkpoints_after_resolving_contexts(): void
    {
        [$article, $job] = $this->makeArticleAndJobAtIllustrationStage(
            $this->makeDraftWithContentDom()
        );

        // First run: resolves and persists contexts, then checkpoints.
        $result = $job->runIllustrationStage();
        $article->refresh();

        $this->assertNull($result);
        $illustrationData = $article->stage_data->getIllustrationStageData();
        $this->assertNotEmpty($illustrationData->getIllustrationContexts());
        $this->assertEmpty($illustrationData->getIllustrationResults());
    }

    public function test_each_generation_run_adds_one_result_and_checkpoints(): void
    {
        [$article, $job] = $this->makeArticleAndJobAtIllustrationStage(
            $this->makeDraftWithContentDom()
        );

        // Run 1: context resolution.
        $job->runIllustrationStage();
        $article->refresh();

        $contextCount = $article->stage_data->getIllustrationStageData()->getIllustrationContexts();
        $this->assertNotEmpty($contextCount);

        // Subsequent runs: each context first checkpoints on direction, then on generation.
        $generationCheckpointCount = 0;
        for ($i = 0; $i < 20; $i++) {
            $stageDataBefore = $article->stage_data->getIllustrationStageData();
            $resultsBefore = count($stageDataBefore->getIllustrationResults());
            $directionsBefore = count($stageDataBefore->getIllustrationDirections());
            $result = $job->runIllustrationStage();
            $article->refresh();

            $stageDataAfter = $article->stage_data->getIllustrationStageData();
            $resultsAfter = count($stageDataAfter->getIllustrationResults());
            $directionsAfter = count($stageDataAfter->getIllustrationDirections());

            if ($result === null && $stageDataAfter->getIllustrationContexts() !== []
                && ! $stageDataAfter->isIllustrationAnchorsResolved()
            ) {
                if ($resultsAfter === $resultsBefore + 1) {
                    // Generation checkpoint.
                    $this->assertSame($directionsBefore, $directionsAfter);
                    $generationCheckpointCount++;
                } else {
                    // Direction checkpoint.
                    $this->assertSame($resultsBefore, $resultsAfter);
                    $this->assertSame($directionsBefore + 1, $directionsAfter);
                }

                continue;
            }

            break;
        }

        $this->assertGreaterThan(0, $generationCheckpointCount, 'At least one generation checkpoint should have occurred.');
    }

    public function test_anchor_resolution_checkpoints_after_generation_completes(): void
    {
        [$article, $job] = $this->makeArticleAndJobAtIllustrationStage(
            $this->makeDraftWithContentDom()
        );

        // Drive past context resolution and all generation runs.
        $result = $this->runUntilAnchorPhase($job, $article);

        $this->assertNull($result);
        $article->refresh();
        $illustrationData = $article->stage_data->getIllustrationStageData();
        $this->assertTrue($illustrationData->isIllustrationAnchorsResolved());
        $this->assertNotEmpty($illustrationData->getIllustrationAnchors());
    }

    public function test_final_run_applies_anchors_and_returns_true(): void
    {
        [$article, $job] = $this->makeArticleAndJobAtIllustrationStage(
            $this->makeDraftWithContentDom()
        );

        $result = $this->runToCompletion($job, $article);

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // End-state assertions
    // -------------------------------------------------------------------------

    public function test_illustration_stage_inserts_img_elements_into_article_dom(): void
    {
        [$article, $job] = $this->makeArticleAndJobAtIllustrationStage(
            $this->makeDraftWithContentDom()
        );

        $this->runToCompletion($job, $article);
        $article->refresh();

        $this->assertInstanceOf(DOMArticle::class, $article->article);
        $this->assertStringContainsString('<img', $article->article->toHtml());
        $this->assertStringContainsString('src=', $article->article->toHtml());
    }

    public function test_illustration_stage_persists_results_in_stage_data(): void
    {
        [$article, $job] = $this->makeArticleAndJobAtIllustrationStage(
            $this->makeDraftWithContentDom()
        );

        $this->runToCompletion($job, $article);
        $article->refresh();

        $illustrationData = $article->stage_data->getIllustrationStageData();
        $this->assertNotEmpty($illustrationData->getIllustrationResults());
        foreach ($illustrationData->getIllustrationResults() as $result) {
            $this->assertInstanceOf(IllustrationResult::class, $result);
            $this->assertNotNull($result->getSeed());
            $this->assertNotEmpty($result->getFiles());
        }
    }

    public function test_illustration_stage_data_survives_db_reload(): void
    {
        [$article, $job] = $this->makeArticleAndJobAtIllustrationStage(
            $this->makeDraftWithContentDom()
        );

        $this->runToCompletion($job, $article);
        $article->refresh();

        $reloaded = Article::query()->findOrFail($article->id);
        $this->assertInstanceOf(StageData::class, $reloaded->stage_data);

        $original = $article->stage_data->getIllustrationStageData();
        $restored = $reloaded->stage_data->getIllustrationStageData();

        $this->assertNotEmpty($restored->getIllustrationContexts());
        $this->assertTrue($restored->isIllustrationAnchorsResolved());
        $this->assertCount(count($original->getIllustrationResults()), $restored->getIllustrationResults());
        $this->assertSame(
            $original->getIllustrationResults()[0]->getSeed(),
            $restored->getIllustrationResults()[0]->getSeed()
        );
        $this->assertSame(
            $original->getIllustrationResults()[0]->getFiles()[0]->getPath(),
            $restored->getIllustrationResults()[0]->getFiles()[0]->getPath()
        );
    }

    public function test_img_src_matches_result_file_path(): void
    {
        [$article, $job] = $this->makeArticleAndJobAtIllustrationStage(
            $this->makeDraftWithContentDom()
        );

        $this->runToCompletion($job, $article);
        $article->refresh();

        $path = $article->stage_data->getIllustrationStageData()->getIllustrationResults()[0]->getFiles()[0]->getPath();
        $this->assertStringContainsString($path, $article->article->toHtml());
    }

    public function test_single_sentence_dom_produces_exactly_one_result(): void
    {
        $dom = new DOMArticle;
        $dom->addChild((new Element)->setType(ElementType::P)->addChild('One sentence only.'));
        $draft = (new Draft)->setTitle('T')->setExcerpt('E')->setArticle($dom);
        [$article, $job] = $this->makeArticleAndJobAtIllustrationStage($draft);

        $this->runToCompletion($job, $article);
        $article->refresh();

        $results = $article->stage_data->getIllustrationStageData()->getIllustrationResults();
        $this->assertCount(1, $results);
        Storage::assertExists($results[0]->getFiles()[0]->getPath());
    }

    public function test_illustration_stage_is_deterministic_for_same_draft_content(): void
    {
        [$articleA, $jobA] = $this->makeArticleAndJobAtIllustrationStage($this->makeDraftWithContentDom());
        [$articleB, $jobB] = $this->makeArticleAndJobAtIllustrationStage($this->makeDraftWithContentDom());

        $this->runToCompletion($jobA, $articleA);
        $this->runToCompletion($jobB, $articleB);
        $articleA->refresh();
        $articleB->refresh();

        $seedA = $articleA->stage_data->getIllustrationStageData()->getIllustrationResults()[0]->getSeed();
        $seedB = $articleB->stage_data->getIllustrationStageData()->getIllustrationResults()[0]->getSeed();
        $this->assertSame($seedA, $seedB);
    }

    public function test_dummy_png_files_exist_in_storage_after_stage(): void
    {
        [$article, $job] = $this->makeArticleAndJobAtIllustrationStage($this->makeDraftWithContentDom());

        $this->runToCompletion($job, $article);
        $article->refresh();

        foreach ($article->stage_data->getIllustrationStageData()->getIllustrationResults() as $result) {
            foreach ($result->getFiles() as $file) {
                Storage::assertExists($file->getPath());
            }
        }
    }

    // -------------------------------------------------------------------------
    // Full pipeline
    // -------------------------------------------------------------------------

    public function test_full_pipeline_reaches_illustration_stage_and_stores_results(): void
    {
        Bus::fake();
        IntentResolver::shouldReceive('mergeIntents')->andReturn(null);

        $client = $this->makeClient();
        $article = $this->makeArticle($client, 'Build me an article about AI writing tools.');

        for ($i = 0; $i < 500; $i++) {
            (new BuildArticleJob($client, $article->id))->handle();
            $article->refresh();
            if ($article->status === ArticleStatus::READY) {
                break;
            }
        }

        $article->refresh();
        $this->assertSame(ArticleStatus::READY, $article->status);
        $this->assertSame(ArticleStage::FINAL, $article->stage);

        $illustrationData = $article->stage_data->getIllustrationStageData();
        $this->assertNotEmpty($illustrationData->getIllustrationContexts());
        $this->assertTrue($illustrationData->isIllustrationAnchorsResolved());
        $this->assertNotEmpty($illustrationData->getIllustrationResults());

        $stageArray = $article->stage_data->toArray();
        $this->assertArrayHasKey('illustration', $stageArray);

        $this->assertInstanceOf(DOMArticle::class, $article->article);
        $this->assertStringContainsString('<img', $article->article->toHtml());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array{0: Article, 1: BuildArticleJob} */
    protected function makeArticleAndJobAtIllustrationStage(?Draft $draft): array
    {
        $client = $this->makeClient();

        $article = new Article;
        $article->client()->associate($client);
        $article->context = (new ArticleContext)->setMeta(['raw_text' => 'Article context']);
        $article->status = ArticleStatus::UNREADY;
        $article->stage = ArticleStage::ILLUSTRATION;
        $article->stage_status = ArticleStageStatus::PROCESSING;

        $stageData = StageData::fromArray([]);
        if ($draft !== null) {
            $stageData->setDraft($draft);
        }

        $article->stage_data = $stageData;
        $article->save();
        $article->refresh();

        $job = new class($client, $article->id) extends BuildArticleJob
        {
            public function runIllustrationStage(): ?bool
            {
                return $this->handleIllustrationStage();
            }
        };

        return [$article, $job];
    }

    protected function makeDraftWithContentDom(): Draft
    {
        $dom = new DOMArticle;
        $dom->addChild((new Element)->setType(ElementType::H2)->addChild('Introduction'));
        $dom->addChild(
            (new Element)->setType(ElementType::P)
                ->addChild('Artificial intelligence is reshaping how developers write software.')
        );
        $dom->addChild((new Element)->setType(ElementType::H2)->addChild('Conclusion'));
        $dom->addChild(
            (new Element)->setType(ElementType::P)
                ->addChild('In summary, these tools accelerate delivery and reduce cognitive overhead.')
        );

        return (new Draft)
            ->setTitle('AI writing tools')
            ->setExcerpt('An overview of AI-assisted writing.')
            ->setArticle($dom);
    }

    /**
     * Drives the stage until the anchor-resolution checkpoint fires (returns null after anchors
     * are resolved), then returns that null result for the caller to assert on.
     */
    protected function runUntilAnchorPhase(BuildArticleJob $job, Article $article): ?bool
    {
        for ($i = 0; $i < 30; $i++) {
            $result = $job->runIllustrationStage();
            $article->refresh();

            if ($article->stage_data->getIllustrationStageData()->isIllustrationAnchorsResolved()) {
                return $result;
            }

            if ($result === true || $result === false) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Runs the stage to completion (until it returns true or false) and returns the final result.
     */
    protected function runToCompletion(BuildArticleJob $job, Article $article): ?bool
    {
        $result = null;
        for ($i = 0; $i < 30; $i++) {
            $result = $job->runIllustrationStage();
            $article->refresh();
            if ($result !== null) {
                break;
            }
        }

        return $result;
    }

    protected function makeClient(): Client
    {
        $client = new Client;
        $client->name = 'client-'.str()->ulid();
        $client->context = (new ClientContext)->setDescription('Client context for tests');
        $client->save();

        return $client;
    }

    protected function makeArticle(Client $client, string $context): Article
    {
        $article = new Article;
        $article->client()->associate($client);
        $article->context = (new ArticleContext)->setMeta(['raw_text' => $context]);
        $article->status = ArticleStatus::UNREADY;
        $article->stage = ArticleStage::IDEA;
        $article->stage_status = ArticleStageStatus::PENDING;
        $article->save();

        return $article;
    }
}
