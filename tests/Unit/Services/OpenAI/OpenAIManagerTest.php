<?php

namespace Tests\Unit\Services\OpenAI;

use App\Facades\OpenAI;
use App\Services\OpenAI\Drivers\OpenAICompatibleDriver;
use App\Services\OpenAI\Drivers\OpenAIDriver;
use App\Services\OpenAI\OpenAIManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class OpenAIManagerTest extends TestCase
{
    public function test_it_returns_default_driver(): void
    {
        Config::set('openai.default', 'openai');

        $manager = OpenAI::getFacadeRoot();

        $driver = $manager->driver();

        $this->assertInstanceOf(OpenAIDriver::class, $driver);
    }

    public function test_it_returns_specified_driver(): void
    {
        $manager = OpenAI::getFacadeRoot();

        $driver = $manager->driver('openai');

        $this->assertInstanceOf(OpenAIDriver::class, $driver);
    }

    public function test_it_returns_openai_compatible_driver(): void
    {
        $manager = OpenAI::getFacadeRoot();

        $driver = $manager->driver('openai_compatible');

        $this->assertInstanceOf(OpenAICompatibleDriver::class, $driver);
    }

    public function test_it_uses_config_for_driver_creation(): void
    {
        Config::set('openai.drivers.openai', [
            'api_key' => 'test-key',
            'base_url' => 'https://api.openai.com/v1',
            'default_model' => 'gpt-4o',
            'timeout' => 120,
        ]);

        $manager = OpenAI::getFacadeRoot();

        $driver = $manager->driver('openai');

        $this->assertInstanceOf(OpenAIDriver::class, $driver);
    }

    public function test_get_default_driver_returns_openai(): void
    {
        Config::set('openai.default', 'openai');

        $manager = OpenAI::getFacadeRoot();

        $this->assertEquals('openai', $manager->getDefaultDriver());
    }

    public function test_get_default_driver_uses_config(): void
    {
        Config::set('openai.default', 'openai_compatible');

        $manager = OpenAI::getFacadeRoot();

        $this->assertEquals('openai_compatible', $manager->getDefaultDriver());
    }

    public function test_facade_returns_openai_manager_instance(): void
    {
        $manager = OpenAI::getFacadeRoot();

        $this->assertInstanceOf(OpenAIManager::class, $manager);
    }
}
