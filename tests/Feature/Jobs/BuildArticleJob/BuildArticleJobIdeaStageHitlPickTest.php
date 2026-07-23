<?php

namespace Tests\Feature\Jobs\BuildArticleJob;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\TaskAction;
use App\Contracts\HitlGateway\TaskOutput;
use App\Contracts\HitlGateway\TaskStatus;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\Model\Article\Context as ArticleContext;
use App\Contracts\Model\Article\StageData;
use App\Contracts\Model\Client\Context as ClientContext;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\IdeaForge\IdeaPicker;
use App\Contracts\Synthesizer\Synthesizer as SynthesizerContract;
use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use App\Enums\HitlHook;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Facades\Synthesizer;
use App\Jobs\BuildArticleJob;
use App\Models\Article;
use App\Models\Client;
use App\Models\HitlPlatform;
use App\Models\HitlTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildArticleJobIdeaStageHitlPickTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('synthesizer.default', 'basic');
        config()->set('hitlgateway.platform_manager', 'dummy');
        config()->set('hitlgateway.task_agent', 'dummy');
        Synthesizer::clearResolvedInstance('synthesizer.manager');
        app()->forgetInstance('synthesizer.manager');
        app()->forgetInstance(SynthesizerContract::class);
        app()->forgetInstance('hitl_platform_manager.manager');
        app()->forgetInstance('hitl_task_agent.manager');
    }

    public function test_process_idea_pick_skips_hitl_when_hook_not_enabled(): void
    {
        [$article, $job, $picker, $report] = $this->makeArticleJobAndPicker();

        $this->assertNull($job->runIdeaPick(new SemanticContext));
        $article->refresh();

        $this->assertSame(1, $picker->pickCalls);
        $this->assertFalse($picker->lastContext?->has('human_decision'));
        $this->assertSame(0, HitlTask::query()->count());
        $this->assertSame(
            $report->getIdentifier(),
            $article->stage_data->getIdeaStageData()->getPickedIdeaAuditReport()?->getIdentifier()
        );
        $this->assertSame(Temporal::EVERGREEN, $article->temporal);
    }

    public function test_process_idea_pick_awaits_human_when_hook_enabled(): void
    {
        [$article, $job, $picker] = $this->makeArticleJobAndPickerWithHitlHook();

        $this->assertNull($job->runIdeaPick(new SemanticContext));
        $article->refresh();

        $this->assertSame(0, $picker->pickCalls);
        $this->assertNull($article->stage_data->getIdeaStageData()->getPickedIdeaAuditReport());

        $hitlTask = HitlTask::query()->sole();
        $this->assertSame($article->id.'--idea-pick', $hitlTask->internal_reference);
        $this->assertSame(TaskStatus::PENDING, $hitlTask->status);
    }

    public function test_process_idea_pick_feeds_human_decision_into_picker_after_hitl_completes(): void
    {
        [$article, $job, $picker, $report, $platform] = $this->makeArticleJobAndPickerWithHitlHook();

        $this->assertNull($job->runIdeaPick(new SemanticContext));
        $this->assertSame(0, $picker->pickCalls);

        $hitlTask = HitlTask::query()->sole();
        $manager = $platform->getHitlPlatformManager();
        $this->assertNotNull($manager->updateTask(
            $hitlTask->hitl_platform_reference,
            (new TaskAction)
                ->setStatus(TaskStatus::COMPLETED)
                ->setOutput((new TaskOutput)->setContent('Prefer the evergreen observability idea'))
        ));

        $this->assertNull($job->runIdeaPick(new SemanticContext));
        $article->refresh();

        $this->assertSame(1, $picker->pickCalls);
        $this->assertTrue($picker->lastContext?->has('human_decision'));
        $this->assertSame(
            'Prefer the evergreen observability idea',
            $picker->lastContext?->getValue('human_decision')
        );
        $this->assertSame(
            $report->getIdentifier(),
            $article->stage_data->getIdeaStageData()->getPickedIdeaAuditReport()?->getIdentifier()
        );
        $this->assertSame(Temporal::EVERGREEN, $article->temporal);
        $this->assertSame(TaskStatus::COMPLETED, $hitlTask->fresh()->status);
    }

    public function test_process_idea_pick_returns_true_when_already_picked(): void
    {
        [$article, $job, $picker] = $this->makeArticleJobAndPicker();

        $stageData = $article->stage_data;
        $existing = $stageData->getIdeaStageData()->getIdeaAuditReports()[0];
        $stageData->getIdeaStageData()->setPickedIdeaAuditReport($existing);
        $article->stage_data = $stageData;
        $article->save();
        $article->refresh();

        $this->assertTrue($job->runIdeaPick(new SemanticContext));
        $this->assertSame(0, $picker->pickCalls);
        $this->assertSame(0, HitlTask::query()->count());
    }

    public function test_process_idea_pick_fails_when_picker_returns_nothing(): void
    {
        [$article, $job] = $this->makeArticleJobAndPicker(returnReport: false);

        $this->assertFalse($job->runIdeaPick(new SemanticContext));
        $article->refresh();

        $this->assertNull($article->stage_data->getIdeaStageData()->getPickedIdeaAuditReport());
    }

    public function test_has_hitl_hook_requires_platform_manager_and_matching_hook(): void
    {
        $client = $this->makeClient();
        $article = $this->makeArticle($client);
        $job = $this->makeJob($article, new TrackingIdeaPicker(null));

        $this->assertFalse($job->checkHitlHook(HitlHook::BUILD_ARTICLE__IDEA__PICK));

        $platform = $this->makeHitlPlatform([HitlHook::BUILD_ARTICLE__IDEA__PICK]);
        $client->hitlPlatform()->associate($platform);
        $client->save();

        $job = $this->makeJob($article->fresh(), new TrackingIdeaPicker(null));
        $this->assertTrue($job->checkHitlHook(HitlHook::BUILD_ARTICLE__IDEA__PICK));

        $platform->hooks = [];
        $platform->save();

        $job = $this->makeJob($article->fresh(), new TrackingIdeaPicker(null));
        $this->assertFalse($job->checkHitlHook(HitlHook::BUILD_ARTICLE__IDEA__PICK));
    }

    /**
     * @return array{0: Article, 1: BuildArticleJob, 2: TrackingIdeaPicker, 3: IdeaAuditReport}
     */
    protected function makeArticleJobAndPicker(bool $returnReport = true): array
    {
        $client = $this->makeClient();
        $article = $this->makeArticle($client);
        $report = $this->seedAuditReport($article);
        $picker = new TrackingIdeaPicker($returnReport ? $report : null);
        $job = $this->makeJob($article, $picker);

        return [$article, $job, $picker, $report];
    }

    /**
     * @return array{0: Article, 1: BuildArticleJob, 2: TrackingIdeaPicker, 3: IdeaAuditReport, 4: HitlPlatform}
     */
    protected function makeArticleJobAndPickerWithHitlHook(): array
    {
        $platform = $this->makeHitlPlatform([HitlHook::BUILD_ARTICLE__IDEA__PICK]);
        $client = $this->makeClient($platform);
        $article = $this->makeArticle($client);
        $report = $this->seedAuditReport($article);
        $picker = new TrackingIdeaPicker($report);
        $job = $this->makeJob($article, $picker);

        return [$article, $job, $picker, $report, $platform];
    }

    protected function makeJob(Article $article, TrackingIdeaPicker $picker): BuildArticleJob
    {
        $synthesizer = Synthesizer::driver('basic');
        $synthesizer->getIdeaForge()->setIdeaPicker($picker);

        return new class($article, $synthesizer) extends BuildArticleJob
        {
            public function __construct(
                Article $article,
                private SynthesizerContract $testSynthesizer,
            ) {
                parent::__construct($article);
            }

            protected function synthesizer(): SynthesizerContract
            {
                return $this->testSynthesizer;
            }

            public function runIdeaPick(SemanticContext $context): ?bool
            {
                return $this->processIdeaPick($context);
            }

            public function checkHitlHook(HitlHook $hook): bool
            {
                return $this->hasHitlHook($hook);
            }
        };
    }

    protected function seedAuditReport(Article $article): IdeaAuditReport
    {
        $idea = new Idea(
            (new Intent)
                ->setTitle('Observability for lean platform teams')
                ->setDescription('Tracing adoption without slowing feature delivery.')
                ->setLanguage(Language::EN)
                ->setTemporal(Temporal::EVERGREEN)
                ->setTypes([IntentType::INFORMATIONAL])
        );

        $report = new IdeaAuditReport($idea, 0.91, ['Strong fit'], []);

        $stageData = $article->stage_data instanceof StageData
            ? $article->stage_data
            : StageData::fromArray([]);

        $ideaData = $stageData->getIdeaStageData();
        $ideaData->setIdeas([$idea]);
        $ideaData->setIdeaAuditReports([$report]);
        $article->stage_data = $stageData;
        $article->save();
        $article->refresh();

        return $article->stage_data->getIdeaStageData()->getIdeaAuditReports()[0];
    }

    /**
     * @param  list<HitlHook>  $hooks
     */
    protected function makeHitlPlatform(array $hooks): HitlPlatform
    {
        return HitlPlatform::query()->create([
            'name' => 'Test HITL Platform '.uniqid(),
            'driver' => 'dummy',
            'is_active' => true,
            'hooks' => $hooks,
        ]);
    }

    protected function makeClient(?HitlPlatform $platform = null): Client
    {
        $client = new Client;
        $client->name = 'client-'.str()->ulid();
        $client->context = (new ClientContext)->setDescription('Client context');
        if ($platform) {
            $client->hitlPlatform()->associate($platform);
        }
        $client->save();

        return $client;
    }

    protected function makeArticle(Client $client): Article
    {
        $article = new Article;
        $article->client()->associate($client);
        $article->context = (new ArticleContext)->setMeta(['raw_text' => 'Article context']);
        $article->status = ArticleStatus::PROCESSING;
        $article->stage = ArticleStage::IDEA;
        $article->stage_status = ArticleStageStatus::PENDING;
        $article->save();

        return $article;
    }
}

final class TrackingIdeaPicker implements IdeaPicker
{
    public int $pickCalls = 0;

    public ?SemanticContext $lastContext = null;

    /** @var IdeaAuditReport[]|null */
    public ?array $lastReports = null;

    public function __construct(private readonly ?IdeaAuditReport $toReturn) {}

    public function pick(array $ideaAuditReports, SemanticContext $context, int $limit = 1): ?array
    {
        $this->pickCalls++;
        $this->lastContext = $context;
        $this->lastReports = $ideaAuditReports;

        return $this->toReturn instanceof IdeaAuditReport ? [$this->toReturn] : null;
    }
}
