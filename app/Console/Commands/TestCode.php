<?php

namespace App\Console\Commands;

use App\Contracts\IntentResolver\IntentData;
use App\Facades\TextEmbedding;
use App\Utils\Math;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:test-code')]
#[Description('Command description')]
class TestCode extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $baseVector = TextEmbedding::embed('2026 Best Films of the Year: Highlights from Cinema Critiques');

        $keyword1Vector = TextEmbedding::embed('best films of 2026');
        $keyword2Vector = TextEmbedding::embed('2026 cinematic trends');

        $this->info('Cosine 1: '.Math::vectorSimilarity($baseVector->toArray(), $keyword1Vector->toArray()));

        $this->info('Cosine 2: '.Math::vectorSimilarity($baseVector->toArray(), $keyword2Vector->toArray()));
    }
}
