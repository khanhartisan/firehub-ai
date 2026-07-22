<?php

namespace App\Console\Commands;

use App\Contracts\HitlGateway\Message;
use App\Contracts\HitlGateway\TaskAction;
use App\Contracts\HitlGateway\TaskOutput;
use App\Contracts\IntentResolver\Intentable;
use App\Facades\HitlGateway\HitlPlatformManager;
use App\Facades\IntentResolver;
use App\Facades\Platforms\FlyCms;
use App\Facades\TextEmbedding;
use App\Models\File;
use App\Models\Platform;
use App\Utils\Math;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:test-code')]
#[Description('Command description')]
class TestCode extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $firetasks = HitlPlatformManager::driver('firetasks');

        $task = $firetasks->updateTask(
            11627,
            new TaskAction()
                ->setMessage(new Message()->setMessage('test upload file to task output'))
                ->setOutput(
                    new TaskOutput()
                        ->setContent('sample output')
                        ->setFiles(File::query()->where('id', '01ky48r8bpkhw5386rax86f90z')->get())
                )
        );

        dump($task);
    }
}
