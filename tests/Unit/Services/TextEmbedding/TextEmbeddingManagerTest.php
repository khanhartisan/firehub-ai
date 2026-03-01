<?php

namespace Tests\Unit\Services\TextEmbedding;

use App\Services\TextEmbedding\Drivers\AzureTextEmbeddingDriver;
use App\Services\TextEmbedding\Drivers\OpenAITextEmbeddingDriver;
use App\Services\TextEmbedding\TextEmbeddingManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TextEmbeddingManagerTest extends TestCase
{
    public function test_it_returns_default_driver(): void
    {
        Config::set('text_embedding.default', 'openai');

        $manager = app('text_embedding.manager');

        $driver = $manager->driver();

        $this->assertInstanceOf(OpenAITextEmbeddingDriver::class, $driver);
    }

    public function test_it_returns_specified_openai_driver(): void
    {
        $manager = app('text_embedding.manager');

        $driver = $manager->driver('openai');

        $this->assertInstanceOf(OpenAITextEmbeddingDriver::class, $driver);
    }

    public function test_it_returns_specified_azure_driver(): void
    {
        $manager = app('text_embedding.manager');

        $driver = $manager->driver('azure');

        $this->assertInstanceOf(AzureTextEmbeddingDriver::class, $driver);
    }

    public function test_it_uses_config_for_driver_creation(): void
    {
        Config::set('text_embedding.drivers.openai', [
            'provider' => 'openai',
            'model' => 'text-embedding-3-large',
            'dimension' => 3072,
        ]);

        $manager = app('text_embedding.manager');

        $driver = $manager->driver('openai');

        $this->assertInstanceOf(OpenAITextEmbeddingDriver::class, $driver);
    }

    public function test_get_default_driver_returns_configured_value(): void
    {
        Config::set('text_embedding.default', 'openai');

        $manager = app('text_embedding.manager');

        $this->assertSame('openai', $manager->getDefaultDriver());
    }

    public function test_get_default_driver_returns_azure_when_configured(): void
    {
        Config::set('text_embedding.default', 'azure');

        $manager = app('text_embedding.manager');

        $this->assertSame('azure', $manager->getDefaultDriver());
    }

    public function test_facade_returns_text_embedding_manager_instance(): void
    {
        $manager = app('text_embedding.manager');

        $this->assertInstanceOf(TextEmbeddingManager::class, $manager);
    }
}
