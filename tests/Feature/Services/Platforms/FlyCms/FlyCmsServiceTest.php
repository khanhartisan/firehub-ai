<?php

namespace Tests\Feature\Services\Platforms\FlyCms;

use App\Contracts\Platforms\FlyCms\MutationData\WebsiteMutationData\CreateWebsiteData;
use App\Facades\Platforms\FlyCms;
use App\Services\Platforms\FlyCms\FlyCmsManager;
use Tests\TestCase;

class FlyCmsServiceTest extends TestCase
{
    public function test_facade_resolves_flycms_manager(): void
    {
        $manager = FlyCms::getFacadeRoot();

        $this->assertInstanceOf(FlyCmsManager::class, $manager);
    }

    public function test_facade_returns_same_manager_instance(): void
    {
        $managerOne = FlyCms::getFacadeRoot();
        $managerTwo = FlyCms::getFacadeRoot();

        $this->assertSame($managerOne, $managerTwo);
    }

    public function test_facade_forwards_website_calls_to_default_driver(): void
    {
        $websites = FlyCms::listWebsites();

        $this->assertCount(2, $websites);
        $this->assertSame('Sample Blog', $websites[0]->getResourceData()['name']);
    }

    public function test_facade_can_create_and_delete_websites(): void
    {
        $createWebsiteData = (new CreateWebsiteData)->setData([
            'status' => 'active',
            'name' => 'Facade Site',
        ]);

        $created = FlyCms::createWebsite($createWebsiteData);
        $websiteId = $created->getResourceData()['id'];

        $this->assertSame('Facade Site', FlyCms::showWebsite($websiteId)?->getResourceData()['name']);
        $this->assertTrue(FlyCms::deleteWebsite($websiteId));
        $this->assertNull(FlyCms::showWebsite($websiteId));
    }

    public function test_driver_method_returns_resolved_driver(): void
    {
        $driver = FlyCms::driver('pseudo');

        $this->assertSame('https://flycms.test', $driver->getConfig()?->getBaseUrl());
    }
}
