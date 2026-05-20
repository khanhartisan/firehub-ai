<?php

namespace Tests\Feature\Jobs\BuildArticleJob;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\Model\Article\Context as ArticleContext;
use App\Contracts\Model\Article\StageData;
use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\Model\Client\Context as ClientContext;
use App\Contracts\Synthesizer\Editor\Editor;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\Synthesizer as SynthesizerContract;
use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Facades\Synthesizer;
use App\Jobs\BuildArticleJob;
use App\Models\Article;
use App\Models\Author;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildArticleJobIdeaStageAuthorContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('synthesizer.default', 'basic');
        Synthesizer::clearResolvedInstance('synthesizer.manager');
        app()->forgetInstance('synthesizer.manager');
        app()->forgetInstance(SynthesizerContract::class);
    }

    public function test_process_author_context_selection_skips_when_client_has_no_authors(): void
    {
        [$article, $job] = $this->makeArticleAndJobWithPickedIdea();

        $this->assertTrue($job->runAuthorContextSelection());
        $article->refresh();

        $ideaData = $article->stage_data->getIdeaStageData();
        $this->assertFalse($ideaData->hasSelectedAuthorContext());
    }

    public function test_process_author_context_selection_persists_editor_choice(): void
    {
        [$article, $job, $secondContext] = $this->makeArticleAndJobWithPickedIdeaAndAuthors();

        $this->assertTrue($job->runAuthorContextSelection());
        $article->refresh();

        $selected = $article->stage_data->getIdeaStageData()->getSelectedAuthorContext();
        $this->assertInstanceOf(AuthorContext::class, $selected);
        $this->assertSame($secondContext->toArray(), $selected->toArray());
        $this->assertSame(1, $job->trackingEditor()->determineCalls);
    }

    public function test_process_author_context_selection_skips_when_already_selected(): void
    {
        [$article, , , $firstContext] = $this->makeArticleAndJobWithPickedIdeaAndAuthors();

        $stageData = $article->stage_data;
        $stageData->getIdeaStageData()->setSelectedAuthorContext($firstContext);
        $article->stage_data = $stageData;
        $article->save();
        $article->refresh();

        $this->assertTrue($article->stage_data->getIdeaStageData()->hasSelectedAuthorContext());

        $job = $this->makeJob($article->client, $article);
        $this->assertTrue($job->runAuthorContextSelection());
        $article->refresh();

        $this->assertSame(0, $job->trackingEditor()->determineCalls);
        $this->assertSame(
            $firstContext->toArray(),
            $article->stage_data->getIdeaStageData()->getSelectedAuthorContext()->toArray()
        );
    }

    public function test_process_author_context_selection_fails_without_picked_idea(): void
    {
        $client = $this->makeClient();
        $article = $this->makeArticle($client);
        $job = $this->makeJob($client, $article);

        $this->assertFalse($job->runAuthorContextSelection());
    }

    /**
     * @return array{0: Article, 1: BuildArticleJob}
     */
    protected function makeArticleAndJobWithPickedIdea(): array
    {
        $client = $this->makeClient();
        $article = $this->makeArticle($client);
        $this->seedPickedIdea($article);

        return [$article, $this->makeJob($client, $article)];
    }

    /**
     * @return array{0: Article, 1: BuildArticleJob, 2: AuthorContext, 3: AuthorContext}
     */
    protected function makeArticleAndJobWithPickedIdeaAndAuthors(): array
    {
        [$article, $job] = $this->makeArticleAndJobWithPickedIdea();
        $client = $article->client;

        $firstContext = (new AuthorContext)->set('voice', 'First voice', 'Formal analyst tone');
        $secondContext = (new AuthorContext)->set('voice', 'Second voice', 'Practical operator tone');

        $this->makeAuthor($client, 'Author A', $firstContext);
        $this->makeAuthor($client, 'Author B', $secondContext);

        return [$article, $job, $secondContext, $firstContext];
    }

    protected function makeJob(Client $client, Article $article): BuildArticleJob
    {
        $editor = new TrackingEditor;
        $synthesizer = Synthesizer::driver('basic')->setEditor($editor);

        return new class($client, $article->id, $editor, $synthesizer) extends BuildArticleJob
        {
            public function __construct(
                Client $client,
                string $articleId,
                private TrackingEditor $editor,
                private SynthesizerContract $testSynthesizer,
            ) {
                parent::__construct($client, $articleId);
            }

            protected function synthesizer(): SynthesizerContract
            {
                return $this->testSynthesizer;
            }

            public function runAuthorContextSelection(): ?bool
            {
                return $this->processAuthorContextSelection();
            }

            public function trackingEditor(): TrackingEditor
            {
                return $this->editor;
            }
        };
    }

    protected function seedPickedIdea(Article $article): void
    {
        $idea = new Idea(
            (new Intent)
                ->setTitle('Observability for lean platform teams')
                ->setDescription('Tracing adoption without slowing feature delivery.')
                ->setLanguage(Language::EN)
                ->setTemporal(Temporal::EVERGREEN)
                ->setTypes([IntentType::INFORMATIONAL])
        );

        $stageData = $article->stage_data instanceof StageData
            ? $article->stage_data
            : StageData::fromArray([]);

        $stageData->getIdeaStageData()->setPickedIdeaAuditReport(
            new IdeaAuditReport($idea, 0.85, ['Strong fit'], [])
        );

        $article->stage_data = $stageData;
        $article->save();
        $article->refresh();
    }

    protected function makeClient(): Client
    {
        $client = new Client;
        $client->name = 'client-'.str()->ulid();
        $client->context = (new ClientContext)->setDescription('Client context');
        $client->save();

        return $client;
    }

    protected function makeArticle(Client $client): Article
    {
        $article = new Article;
        $article->client()->associate($client);
        $article->context = (new ArticleContext)->setMeta(['raw_text' => 'Article context']);
        $article->status = ArticleStatus::UNREADY;
        $article->stage = ArticleStage::IDEA;
        $article->stage_status = ArticleStageStatus::PENDING;
        $article->save();

        return $article;
    }

    protected function makeAuthor(Client $client, string $name, AuthorContext $context): Author
    {
        $author = new Author;
        $author->client()->associate($client);
        $author->name = $name;
        $author->context = $context;
        $author->save();

        return $author;
    }
}

final class TrackingEditor implements Editor
{
    public int $determineCalls = 0;

    public function determineAuthorContext(Idea $idea, array $authorContexts): SemanticContext
    {
        $this->determineCalls++;

        return $authorContexts[1] ?? $authorContexts[0];
    }

    public function distillOutlineAuthorContext(
        Outline $outline,
        string $outlineItemIdentifier,
        SemanticContext $authorContext,
        ?SemanticContext $generalContext = null
    ): SemanticContext {
        return $authorContext;
    }
}
