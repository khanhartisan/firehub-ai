<?php

namespace Tests\Unit\Services\Synthesizer\Writer;

use App\Services\Synthesizer\Writer\Drivers\BasicWriterDriver;
use App\Services\Synthesizer\Writer\Drivers\OpenAIWriterDriver;
use App\Services\Synthesizer\Writer\WriterManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WriterManagerTest extends TestCase
{
    public function test_it_resolves_basic_driver_by_name(): void
    {
        $manager = $this->app->make(WriterManager::class);

        $this->assertInstanceOf(BasicWriterDriver::class, $manager->driver('basic'));
    }

    public function test_it_uses_subservice_default_when_no_driver_given(): void
    {
        Config::set('synthesizer.writer.default', 'basic');

        $manager = $this->app->make(WriterManager::class);

        $this->assertInstanceOf(BasicWriterDriver::class, $manager->driver());
    }

    public function test_it_resolves_openai_driver_by_name(): void
    {
        $manager = $this->app->make(WriterManager::class);

        $this->assertInstanceOf(OpenAIWriterDriver::class, $manager->driver('openai'));
    }
}
