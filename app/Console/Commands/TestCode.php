<?php

namespace App\Console\Commands;

use App\Contracts\IntentResolver\Intentable;
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
        $intentable = (new Intentable)->setContent(Storage::get('live-tests/sample-page-for-intent-resolver-service.md'));
        $intentData = IntentResolver::resolve($intentable);
        $intent = $intentData->getPrimaryIntent();
        if ($intent === null) {
            $this->error('No primary intent from resolve().');

            return self::FAILURE;
        }
        $intentVector = TextEmbedding::embed($intent->getTitle()."\n".$intent->getDescription());
        $this->line($intent->getTitle());
        $this->line($intent->getDescription());
        $this->line('----------');

        $intentable2 = (new Intentable)->setContent(Storage::get('live-tests/sample-page-for-intent-resolver-service.md'));
        $intentData2 = IntentResolver::resolve($intentable2);
        $intent2 = $intentData2->getPrimaryIntent();
        if ($intent2 === null) {
            $this->error('No primary intent from resolve() (second run).');

            return self::FAILURE;
        }
        $intentVector2 = TextEmbedding::embed($intent2->getTitle()."\n".$intent2->getDescription());
        $this->line($intent2->getTitle());
        $this->line($intent2->getDescription());
        $this->line('----------');

        $this->info('Cosine: '.Math::vectorSimilarity($intentVector->toArray(), $intentVector2->toArray()));
    }
}
