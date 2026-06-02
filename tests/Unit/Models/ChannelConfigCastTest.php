<?php

namespace Tests\Unit\Models;

use App\Contracts\PlatformManager\Config;
use App\Contracts\PlatformManager\PlatformManager as PlatformManagerContract;
use App\Models\Channel;
use App\Models\Platform;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Tests\TestCase;

class ChannelConfigCastTest extends TestCase
{
    public function test_it_hydrates_config_instance_from_json_value(): void
    {
        $channel = new Channel;
        $channel->setRelation('platform', $this->mockPlatform(new FakePlatformManager));
        $channel->setRawAttributes([
            'config' => json_encode(['website_id' => 'site-123']),
        ], true);

        $this->assertInstanceOf(FakeChannelConfig::class, $channel->config);
        $this->assertSame(['website_id' => 'site-123'], $channel->config->toArray());
    }

    public function test_it_dehydrates_array_payload_through_platform_manager_config(): void
    {
        $channel = new Channel;
        $channel->setRelation('platform', $this->mockPlatform(new FakePlatformManager));

        $channel->config = ['website_id' => 'site-456'];

        $this->assertIsString($channel->getAttributes()['config']);
        $this->assertSame(['website_id' => 'site-456'], json_decode($channel->getAttributes()['config'], true));
        $this->assertInstanceOf(FakeChannelConfig::class, $channel->config);
    }

    public function test_it_returns_null_when_platform_manager_has_no_channel_config(): void
    {
        $channel = new Channel;
        $channel->setRelation('platform', $this->mockPlatform(new NullChannelConfigPlatformManager));

        $channel->config = ['website_id' => 'site-789'];

        $this->assertNull($channel->getAttributes()['config']);
        $this->assertNull($channel->config);
    }

    private function mockPlatform(PlatformManagerContract $platformManager): Platform
    {
        $platform = \Mockery::mock(Platform::class)->makePartial();
        $platform->shouldReceive('getPlatformManager')
            ->andReturn($platformManager);

        return $platform;
    }
}

class FakePlatformManager implements PlatformManagerContract
{
    public function setConfig(Config $config): static
    {
        return $this;
    }

    public function getConfig(): ?Config
    {
        return null;
    }

    public function makeChannelConfig(): ?Config
    {
        return new FakeChannelConfig;
    }
}

class NullChannelConfigPlatformManager extends FakePlatformManager
{
    public function makeChannelConfig(): ?Config
    {
        return null;
    }
}

class FakeChannelConfig extends Config
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'website_id' => $schema->string()->required(),
        ];
    }
}
