<?php

namespace Tests\Unit\Models;

use App\Contracts\PlatformManager\Config;
use App\Contracts\PlatformManager\PlatformManager as PlatformManagerContract;
use App\Enums\PlatformType;
use App\Models\Platform;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Tests\TestCase;

class PlatformConfigCastTest extends TestCase
{
    public function test_it_hydrates_config_instance_from_json_value(): void
    {
        $platform = $this->mockPlatform(new PlatformCastFakePlatformManager);
        $platform->type = PlatformType::FLYCMS;
        $platform->setRawAttributes([
            'type' => PlatformType::FLYCMS->value,
            'config' => json_encode(['base_url' => 'https://example.test', 'api_key' => 'key-123']),
        ], true);

        $this->assertInstanceOf(PlatformCastFakePlatformConfig::class, $platform->config);
        $this->assertSame(['base_url' => 'https://example.test', 'api_key' => 'key-123'], $platform->config->toArray());
    }

    public function test_it_dehydrates_array_payload_through_platform_manager_config(): void
    {
        $platform = $this->mockPlatform(new PlatformCastFakePlatformManager);
        $platform->type = PlatformType::FLYCMS;

        $platform->config = ['base_url' => 'https://example.test', 'api_key' => 'key-456'];

        $this->assertIsString($platform->getAttributes()['config']);
        $this->assertSame(
            ['base_url' => 'https://example.test', 'api_key' => 'key-456'],
            json_decode($platform->getAttributes()['config'], true)
        );
        $this->assertInstanceOf(PlatformCastFakePlatformConfig::class, $platform->config);
    }

    public function test_it_returns_null_when_stored_value_is_null(): void
    {
        $platform = $this->mockPlatform(new PlatformCastFakePlatformManager);
        $platform->type = PlatformType::FLYCMS;
        $platform->setRawAttributes([
            'type' => PlatformType::FLYCMS->value,
            'config' => null,
        ], true);

        $this->assertNull($platform->config);
    }

    public function test_it_returns_null_when_platform_manager_has_no_platform_config(): void
    {
        $platform = $this->mockPlatform(new PlatformCastNullPlatformConfigPlatformManager);
        $platform->type = PlatformType::FLYCMS;

        $platform->config = ['base_url' => 'https://example.test', 'api_key' => 'key-789'];

        $this->assertNull($platform->getAttributes()['config']);
        $this->assertNull($platform->config);
    }

    private function mockPlatform(PlatformManagerContract $platformManager): Platform
    {
        $platform = \Mockery::mock(Platform::class)->makePartial();
        $platform->shouldReceive('getPlatformManager')
            ->andReturn($platformManager);

        return $platform;
    }
}

class PlatformCastFakePlatformManager implements PlatformManagerContract
{
    public function setConfig(Config $config): static
    {
        return $this;
    }

    public function getConfig(): ?Config
    {
        return null;
    }

    public function makeConfig(): ?Config
    {
        return new PlatformCastFakePlatformConfig;
    }

    public function makeChannelConfig(): ?Config
    {
        return null;
    }
}

class PlatformCastNullPlatformConfigPlatformManager extends PlatformCastFakePlatformManager
{
    public function makeConfig(): ?Config
    {
        return null;
    }
}

class PlatformCastFakePlatformConfig extends Config
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'base_url' => $schema->string()->required(),
            'api_key' => $schema->string()->required(),
        ];
    }
}
