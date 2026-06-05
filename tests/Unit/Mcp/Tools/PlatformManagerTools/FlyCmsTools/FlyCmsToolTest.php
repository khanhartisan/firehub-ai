<?php

namespace Tests\Unit\Mcp\Tools\PlatformManagerTools\FlyCmsTools;

use App\Contracts\PlatformManager\FlyCms\Config as FlyCmsConfig;
use App\Enums\PlatformType;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools\ShowWebsiteTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use App\Services\PlatformManager\FlyCms\Drivers\PseudoFlyCmsDriver;
use App\Utils\Json;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlyCmsToolTest extends TestCase
{
    use RefreshDatabase;

    private const MASTER_API_KEY = 'master-api-key';

    public function test_get_flycms_manager_with_user_returns_new_instance_using_user_api_key(): void
    {
        $tool = new ShowWebsiteTool;
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel();

        $masterFlycms = $tool->getFlyCmsManager($channel);
        $userFlycms = $tool->getFlyCmsManager($channel, $user);

        $this->assertInstanceOf(PseudoFlyCmsDriver::class, $userFlycms);
        $this->assertNotSame($masterFlycms, $userFlycms);
        $this->assertSame(self::MASTER_API_KEY, $masterFlycms->getConfig()?->getApiKey());
        $this->assertNotSame(self::MASTER_API_KEY, $userFlycms->getConfig()?->getApiKey());
        $this->assertNotNull($userFlycms->getConfig()?->getApiKey());
        $this->assertSame(
            'https://flycms.example.test',
            $userFlycms->getConfig()?->getBaseUrl()
        );

        $storedUserData = Json::decode(
            $channel->platform->fresh()->getMetaValue('user-'.$user->id),
            true
        );

        $this->assertSame($storedUserData['api_key'], $userFlycms->getConfig()?->getApiKey());
    }

    private function createFlyCmsChannel(): Channel
    {
        $platform = new Platform;
        $platform->name = 'Production FlyCMS';
        $platform->type = PlatformType::FLYCMS;
        $platform->config = new FlyCmsConfig([
            'base_url' => 'https://flycms.example.test',
            'api_key' => self::MASTER_API_KEY,
        ]);
        $platform->save();

        $client = new Client;
        $client->name = 'Acme Corp';
        $client->save();

        $channel = new Channel;
        $channel->client_id = $client->id;
        $channel->platform_id = $platform->id;
        $channel->name = 'Main Blog';
        $channel->save();

        return $channel->load('platform');
    }
}
