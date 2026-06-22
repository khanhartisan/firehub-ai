<?php

namespace App\Jobs;

use App\Enums\Queue;
use App\Models\Snapshot;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeStatus;

class ForceDeleteSnapshots implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 60;

    public int $uniqueFor = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue(Queue::DEFAULT->value);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = time();

        while (true) {

            if (time() - $startTime >= $this->timeout - 5) {
                return;
            }

            /** @var Snapshot $snapshot */
            if (!$snapshot = Snapshot::query()
                ->onlyTrashed()
                ->where('cascade_status', CascadeStatus::DELETED)
                ->first()
            ) {
                return;
            }

            if (Storage::exists($snapshot->file_path)) {
                Storage::delete($snapshot->file_path);
            }

            $snapshot->forceDelete();
        }
    }
}
