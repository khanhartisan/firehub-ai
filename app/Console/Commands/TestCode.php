<?php

namespace App\Console\Commands;

use App\Contracts\IntentResolver\IntentData;
use App\Facades\IntentResolver;
use App\Facades\TextEmbedding;
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
        $intentData = IntentResolver::resolve(Storage::get('live-tests/sample-page-for-intent-resolver-service.md'));
        $intentVector = TextEmbedding::embed($intentData->getTitle()."\n".$intentData->getDescription());
        $this->line($intentData->getTitle());
        $this->line($intentData->getDescription());
        $this->line('----------');

        $intentData2 = IntentResolver::resolve(Storage::get('live-tests/sample-page-for-intent-resolver-service.md'));
        $intentVector2 = TextEmbedding::embed($intentData2->getTitle()."\n".$intentData2->getDescription());
        $this->line($intentData2->getTitle());
        $this->line($intentData2->getDescription());
        $this->line('----------');

        $this->info('Cosine: '.Math::vectorSimilarity($intentVector->toArray(), $intentVector2->toArray()));
    }
}
