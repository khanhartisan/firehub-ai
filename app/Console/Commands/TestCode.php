<?php

namespace App\Console\Commands;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\Message;
use App\Contracts\HitlGateway\TaskAction;
use App\Contracts\HitlGateway\TaskOutput;
use App\Contracts\IntentResolver\Intentable;
use App\Facades\HitlGateway;
use App\Facades\HitlGateway\HitlPlatformManager;
use App\Facades\HitlGateway\TaskAgent;
use App\Facades\IntentResolver;
use App\Facades\Platforms\FlyCms;
use App\Facades\TextEmbedding;
use App\Models\File;
use App\Models\HitlPlatform;
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
        $agent = TaskAgent::driver('openai');

        /** @var HitlPlatform $platform */
        $platform = HitlPlatform::query()->firstOrCreate([
            'is_active' => true,
            'driver' => 'firetasks',
            'name' => 'test-firetasks'
        ]);

        $conclusion = HitlGateway::askHuman(
            'test-a-dummy-question-3',
            $platform,
            $agent,
            new SemanticContext()
                ->set('context', 'Bối cảnh của vấn đề', 'Công ty đang tổ chức party và cần đặt bàn')
                ->set('needed_information', 'Thông tin cần được giải quyết', 'Cần biết có tổng bao nhiêu người, bao nhiêu nam, bao nhiêu nữ, bao nhiêu trẻ em')
        );

        dump($conclusion?->toArray() ?? 'Awaiting human...');
    }
}
