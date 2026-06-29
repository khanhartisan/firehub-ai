<?php

namespace App\Console\Commands;

use App\Contracts\IntentResolver\Intentable;
use App\Facades\IntentResolver;
use App\Facades\Platforms\FlyCms;
use App\Facades\TextEmbedding;
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
        $platform = Platform::query()->firstOrFail();
        $flycms = FlyCms::driver();
        $flycms->setConfig($platform->config);

        $websiteId = '01kvygs0jdsmkp82p282p4m0v4';

        dump($flycms->listAuthors($websiteId));
    }
}
