<?php

namespace Tests\Unit\Services\PlatformManager\FlyCms\Drivers;

use App\Contracts\PlatformManager\FlyCms\Config;
use App\Contracts\PlatformManager\FlyCms\Filters\WebsiteFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\CreateWebsiteData;
use App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData\UpdateWebsiteData;
use App\Services\PlatformManager\FlyCms\Drivers\PseudoFlyCmsDriver;
use InvalidArgumentException;
use Tests\TestCase;

class PseudoFlyCmsDriverTest extends TestCase
{
    private PseudoFlyCmsDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new PseudoFlyCmsDriver;
    }

    public function test_it_seeds_sample_websites(): void
    {
        $websites = $this->driver->listWebsites();

        $this->assertCount(2, $websites);
        $this->assertSame('Sample Blog', $websites[0]->getResourceData()['name']);
        $this->assertSame('Demo Storefront', $websites[1]->getResourceData()['name']);
    }

    public function test_show_website_returns_matching_resource(): void
    {
        $website = $this->driver->showWebsite('01J00000000000000000000001');

        $this->assertNotNull($website);
        $this->assertSame('Sample Blog', $website->getResourceData()['name']);
        $this->assertSame('active', $website->getResourceData()['status']);
        $this->assertSame('Sample Blog', $website->getResourceData()['meta']['site-name']);
    }

    public function test_show_website_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->driver->showWebsite('unknown-id'));
    }

    public function test_create_website_persists_in_memory(): void
    {
        $createWebsiteData = (new CreateWebsiteData)->setData([
            'status' => 'active',
            'name' => 'New Site',
            'post_route' => '/posts/{post}',
        ]);

        $created = $this->driver->createWebsite($createWebsiteData);
        $data = $created->getResourceData();

        $this->assertSame('New Site', $data['name']);
        $this->assertSame('/posts/{post}', $data['post_route']);
        $this->assertNotEmpty($data['id']);
        $this->assertSame(0, $data['domains_count']);
        $this->assertSame(0, $data['public_posts_count']);
        $this->assertNotNull($this->driver->showWebsite($data['id']));
        $this->assertCount(3, $this->driver->listWebsites());
    }

    public function test_update_website_merges_changes(): void
    {
        $updateWebsiteData = (new UpdateWebsiteData)->setData([
            'name' => 'Renamed Blog',
            'status' => 'inactive',
        ]);

        $updated = $this->driver->updateWebsite('01J00000000000000000000001', $updateWebsiteData);
        $data = $updated->getResourceData();

        $this->assertSame('Renamed Blog', $data['name']);
        $this->assertSame('inactive', $data['status']);
        $this->assertSame('/assets/{path}', $data['asset_route']);
        $this->assertSame('Renamed Blog', $this->driver->showWebsite('01J00000000000000000000001')?->getResourceData()['name']);
    }

    public function test_update_website_throws_for_unknown_id(): void
    {
        $updateWebsiteData = (new UpdateWebsiteData)->setData([
            'status' => 'active',
            'name' => 'Missing Site',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Website [missing-id] not found.');

        $this->driver->updateWebsite('missing-id', $updateWebsiteData);
    }

    public function test_delete_website_removes_record(): void
    {
        $this->assertTrue($this->driver->deleteWebsite('01J00000000000000000000001'));
        $this->assertNull($this->driver->showWebsite('01J00000000000000000000001'));
        $this->assertCount(1, $this->driver->listWebsites());
    }

    public function test_delete_website_returns_false_for_unknown_id(): void
    {
        $this->assertFalse($this->driver->deleteWebsite('missing-id'));
    }

    public function test_list_websites_filters_by_search(): void
    {
        $filter = (new WebsiteFilter)->setFilterData([
            'search' => 'demo',
        ]);

        $websites = $this->driver->listWebsites(websiteFilter: $filter);

        $this->assertCount(1, $websites);
        $this->assertSame('Demo Storefront', $websites[0]->getResourceData()['name']);
    }

    public function test_list_websites_filters_by_ids(): void
    {
        $filter = (new WebsiteFilter)->setFilterData([
            'ids' => '01J00000000000000000000002',
        ]);

        $websites = $this->driver->listWebsites(websiteFilter: $filter);

        $this->assertCount(1, $websites);
        $this->assertSame('01J00000000000000000000002', $websites[0]->getResourceData()['id']);
    }

    public function test_list_websites_supports_pagination(): void
    {
        $pageOne = $this->driver->listWebsites(page: 1, limit: 1);
        $pageTwo = $this->driver->listWebsites(page: 2, limit: 1);

        $this->assertCount(1, $pageOne);
        $this->assertCount(1, $pageTwo);
        $this->assertNotSame(
            $pageOne[0]->getResourceData()['id'],
            $pageTwo[0]->getResourceData()['id']
        );
    }

    public function test_set_config_and_get_config(): void
    {
        $config = new Config([
            'base_url' => 'https://example.test',
            'api_key' => 'test-key',
        ]);

        $this->driver->setConfig($config);

        $this->assertSame('https://example.test', $this->driver->getConfig()?->getBaseUrl());
        $this->assertSame('test-key', $this->driver->getConfig()?->getApiKey());
    }
}
