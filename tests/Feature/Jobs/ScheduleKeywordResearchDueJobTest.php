<?php

namespace Tests\Feature\Jobs;

use App\Enums\KeywordStatus;
use App\Enums\Queue as QueueEnum;
use App\Jobs\KeywordResearchJob;
use App\Jobs\ScheduleKeywordResearchDueJob;
use App\Models\Keyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduleKeywordResearchDueJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('queue.max_keyword_researching_queue_size', 1000);
    }

    public function test_dispatches_only_stale_researching_keywords(): void
    {
        Queue::fake();

        $staleResearching = Keyword::query()->create([
            'keyword' => 'stale researching',
            'hash' => sha1('stale researching'),
            'status' => KeywordStatus::RESEARCHING,
        ]);

        $freshResearching = Keyword::query()->create([
            'keyword' => 'fresh researching',
            'hash' => sha1('fresh researching'),
            'status' => KeywordStatus::RESEARCHING,
        ]);

        $stalePending = Keyword::query()->create([
            'keyword' => 'stale pending',
            'hash' => sha1('stale pending'),
            'status' => KeywordStatus::PENDING,
        ]);

        DB::table('keywords')->where('id', $staleResearching->id)->update([
            'updated_at' => now()->subMinutes(16),
        ]);
        DB::table('keywords')->where('id', $freshResearching->id)->update([
            'updated_at' => now()->subMinutes(5),
        ]);
        DB::table('keywords')->where('id', $stalePending->id)->update([
            'updated_at' => now()->subMinutes(30),
        ]);

        (new ScheduleKeywordResearchDueJob)->handle();

        Queue::assertPushedTimes(KeywordResearchJob::class, 1);
        Queue::assertPushedOn(QueueEnum::KEYWORD_RESEARCHING->value, KeywordResearchJob::class);
        Queue::assertPushed(KeywordResearchJob::class, function (KeywordResearchJob $job) use ($staleResearching): bool {
            return $job->uniqueId() === $staleResearching->id;
        });
    }

    public function test_does_not_dispatch_when_keyword_researching_queue_has_no_slots(): void
    {
        Config::set('queue.max_keyword_researching_queue_size', 0);
        Queue::fake();

        $staleResearching = Keyword::query()->create([
            'keyword' => 'stale researching no slots',
            'hash' => sha1('stale researching no slots'),
            'status' => KeywordStatus::RESEARCHING,
        ]);

        DB::table('keywords')->where('id', $staleResearching->id)->update([
            'updated_at' => now()->subMinutes(16),
        ]);

        (new ScheduleKeywordResearchDueJob)->handle();

        Queue::assertNotPushed(KeywordResearchJob::class);
    }
}
