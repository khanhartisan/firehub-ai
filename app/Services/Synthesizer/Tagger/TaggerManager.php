<?php

namespace App\Services\Synthesizer\Tagger;

use App\Services\Synthesizer\Support\SubserviceManager;
use App\Services\Synthesizer\Tagger\Drivers\BasicTaggerDriver;

class TaggerManager extends SubserviceManager
{
    protected function configKey(): string
    {
        return 'tagger';
    }

    protected function createBasicDriver(): BasicTaggerDriver
    {
        return new BasicTaggerDriver;
    }
}
