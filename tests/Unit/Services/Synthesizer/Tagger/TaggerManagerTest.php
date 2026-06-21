<?php

namespace Tests\Unit\Services\Synthesizer\Tagger;

use App\Services\Synthesizer\Tagger\Drivers\BasicTaggerDriver;
use App\Services\Synthesizer\Tagger\Drivers\OpenAICompatibleTaggerDriver;
use App\Services\Synthesizer\Tagger\Drivers\OpenAITaggerDriver;
use App\Services\Synthesizer\Tagger\TaggerManager;
use Tests\TestCase;

class TaggerManagerTest extends TestCase
{
    public function test_it_resolves_basic_driver(): void
    {
        $manager = $this->app->make(TaggerManager::class);

        $driver = $manager->driver('basic');

        $this->assertInstanceOf(BasicTaggerDriver::class, $driver);
    }

    public function test_it_resolves_openai_driver(): void
    {
        $manager = $this->app->make(TaggerManager::class);

        $driver = $manager->driver('openai');

        $this->assertInstanceOf(OpenAITaggerDriver::class, $driver);
    }

    public function test_it_resolves_openai_compatible_driver(): void
    {
        $manager = $this->app->make(TaggerManager::class);

        $driver = $manager->driver('openai_compatible');

        $this->assertInstanceOf(OpenAICompatibleTaggerDriver::class, $driver);
    }
}
