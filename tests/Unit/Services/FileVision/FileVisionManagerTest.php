<?php

namespace Tests\Unit\Services\FileVision;

use App\Contracts\OpenAI\OpenAIClient;
use App\Facades\FileVision;
use App\Services\FileVision\Drivers\BasicFileVisionDriver;
use App\Services\FileVision\Drivers\OpenAICompatibleFileVisionDriver;
use App\Services\FileVision\Drivers\OpenAIFileVisionDriver;
use App\Services\FileVision\FileVisionManager;
use App\Services\OpenAI\OpenAIManager;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class FileVisionManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    public function test_it_returns_default_driver(): void
    {
        Config::set('filevision.default', 'basic');

        $manager = FileVision::getFacadeRoot();

        $driver = $manager->driver();

        $this->assertInstanceOf(BasicFileVisionDriver::class, $driver);
    }

    public function test_it_returns_specified_driver(): void
    {
        $manager = FileVision::getFacadeRoot();

        $driver = $manager->driver('basic');

        $this->assertInstanceOf(BasicFileVisionDriver::class, $driver);
    }

    public function test_it_returns_openai_driver(): void
    {
        // Mock OpenAI client for the driver
        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $this->app->instance(OpenAIClient::class, $mockOpenAIClient);

        $manager = FileVision::getFacadeRoot();

        $driver = $manager->driver('openai');

        $this->assertInstanceOf(OpenAIFileVisionDriver::class, $driver);
    }

    public function test_it_returns_openai_compatible_driver(): void
    {
        $mockOpenAIManager = Mockery::mock(OpenAIManager::class);
        $mockOpenAIClient = Mockery::mock(\App\Contracts\OpenAI\OpenAIClient::class);
        $mockOpenAIManager->shouldReceive('driver')->with('openai_compatible')->andReturn($mockOpenAIClient);
        $this->app->instance('openai.manager', $mockOpenAIManager);

        $manager = FileVision::getFacadeRoot();

        $driver = $manager->driver('openai_compatible');

        $this->assertInstanceOf(OpenAICompatibleFileVisionDriver::class, $driver);
    }

    public function test_it_uses_config_for_driver_creation(): void
    {
        Config::set('filevision.drivers.basic', [
            'custom_option' => 'test-value',
        ]);

        $manager = FileVision::getFacadeRoot();

        $driver = $manager->driver('basic');

        $this->assertInstanceOf(BasicFileVisionDriver::class, $driver);
    }

    public function test_it_uses_config_for_openai_driver(): void
    {
        // Mock OpenAI client for the driver
        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $this->app->instance(OpenAIClient::class, $mockOpenAIClient);

        Config::set('filevision.drivers.openai', [
            'model' => 'gpt-4o',
        ]);

        $manager = FileVision::getFacadeRoot();

        $driver = $manager->driver('openai');

        $this->assertInstanceOf(OpenAIFileVisionDriver::class, $driver);
    }

    public function test_get_default_driver_returns_basic(): void
    {
        Config::set('filevision.default', 'basic');

        $manager = FileVision::getFacadeRoot();

        $this->assertEquals('basic', $manager->getDefaultDriver());
    }

    public function test_get_default_driver_uses_config(): void
    {
        Config::set('filevision.default', 'openai');

        $manager = FileVision::getFacadeRoot();

        $this->assertEquals('openai', $manager->getDefaultDriver());
    }

    public function test_facade_returns_filevision_manager_instance(): void
    {
        $manager = FileVision::getFacadeRoot();

        $this->assertInstanceOf(FileVisionManager::class, $manager);
    }
}
