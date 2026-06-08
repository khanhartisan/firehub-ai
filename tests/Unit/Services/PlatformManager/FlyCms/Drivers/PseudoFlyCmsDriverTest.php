<?php

namespace Tests\Unit\Services\PlatformManager\FlyCms\Drivers;

use App\Contracts\PlatformManager\FlyCms\Config;
use App\Contracts\PlatformManager\FlyCms\Filters\DomainFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\FileFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\PostFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\RoleFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\TagFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\ThemeFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\UserFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\WebsiteFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\CreateFileData;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\UpdateFileData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\CreatePostData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\UpdatePostData;
use App\Contracts\PlatformManager\FlyCms\MutationData\RoleMutationData\CreateRoleData;
use App\Contracts\PlatformManager\FlyCms\MutationData\RoleMutationData\UpdateRoleData;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\CreateMenuData;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\UpdateMenuData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\CreatePageData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\UpdatePageData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\CreateTagData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\UpdateTagData;
use App\Contracts\PlatformManager\FlyCms\MutationData\UserMutationData\CreateUserData;
use App\Contracts\PlatformManager\FlyCms\MutationData\UserMutationData\UpdateUserData;
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
        $this->assertSame('Sample Blog', $websites[0]->getData()['name']);
        $this->assertSame('Demo Storefront', $websites[1]->getData()['name']);
    }

    public function test_show_website_returns_matching_resource(): void
    {
        $website = $this->driver->showWebsite('01J00000000000000000000001');

        $this->assertNotNull($website);
        $this->assertSame('Sample Blog', $website->getData()['name']);
        $this->assertSame('active', $website->getData()['status']);
        $this->assertSame('01J00000000000000000000081', $website->getData()['theme_id']);
        $this->assertSame('Sample Blog', $website->getData()['meta']['site-name']);
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
            'theme_id' => '01J00000000000000000000083',
        ]);

        $created = $this->driver->createWebsite($createWebsiteData);
        $data = $created->getData();

        $this->assertSame('New Site', $data['name']);
        $this->assertSame('/posts/{post}', $data['post_route']);
        $this->assertSame('01J00000000000000000000083', $data['theme_id']);
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
            'theme_id' => '01J00000000000000000000082',
        ]);

        $updated = $this->driver->updateWebsite('01J00000000000000000000001', $updateWebsiteData);
        $data = $updated->getData();

        $this->assertSame('Renamed Blog', $data['name']);
        $this->assertSame('inactive', $data['status']);
        $this->assertSame('01J00000000000000000000082', $data['theme_id']);
        $this->assertSame('/assets/{path}', $data['asset_route']);
        $this->assertSame('Renamed Blog', $this->driver->showWebsite('01J00000000000000000000001')?->getData()['name']);
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
        $this->assertSame('Demo Storefront', $websites[0]->getData()['name']);
    }

    public function test_list_websites_filters_by_ids(): void
    {
        $filter = (new WebsiteFilter)->setFilterData([
            'ids' => '01J00000000000000000000002',
        ]);

        $websites = $this->driver->listWebsites(websiteFilter: $filter);

        $this->assertCount(1, $websites);
        $this->assertSame('01J00000000000000000000002', $websites[0]->getData()['id']);
    }

    public function test_list_websites_supports_pagination(): void
    {
        $pageOne = $this->driver->listWebsites(page: 1, limit: 1);
        $pageTwo = $this->driver->listWebsites(page: 2, limit: 1);

        $this->assertCount(1, $pageOne);
        $this->assertCount(1, $pageTwo);
        $this->assertNotSame(
            $pageOne[0]->getData()['id'],
            $pageTwo[0]->getData()['id']
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

    public function test_reset_restores_seeded_data(): void
    {
        $this->driver->deleteWebsite('01J00000000000000000000001');

        $this->assertCount(1, $this->driver->listWebsites());

        PseudoFlyCmsDriver::reset();

        $this->assertCount(2, $this->driver->listWebsites());
    }

    public function test_clone_shares_in_memory_store_with_parent(): void
    {
        $clone = $this->driver->clone();

        $this->assertNotSame($this->driver, $clone);

        $updateWebsiteData = (new UpdateWebsiteData)->setData([
            'name' => 'Renamed Via Clone',
        ]);

        $clone->updateWebsite('01J00000000000000000000001', $updateWebsiteData);

        $this->assertSame(
            'Renamed Via Clone',
            $this->driver->showWebsite('01J00000000000000000000001')?->getData()['name']
        );
    }

    public function test_it_seeds_sample_domains(): void
    {
        $domains = $this->driver->listDomains();

        $this->assertCount(3, $domains);
        $this->assertSame('blog.example.com', $domains[0]->getData()['domain']);
        $this->assertTrue($domains[0]->getData()['is_primary']);
        $this->assertSame('shop.demo.test', $domains[2]->getData()['domain']);
    }

    public function test_show_domain_returns_matching_resource(): void
    {
        $domain = $this->driver->showDomain('01J00000000000000000000031');

        $this->assertNotNull($domain);
        $this->assertSame('01J00000000000000000000001', $domain->getData()['website_id']);
        $this->assertSame('blog.example.com', $domain->getData()['domain']);
        $this->assertTrue($domain->getData()['is_primary']);
        $this->assertFalse($domain->getData()['is_alias']);
        $this->assertSame('active', $domain->getData()['status']);
        $this->assertTrue($domain->getData()['is_connected_to_server']);
    }

    public function test_show_domain_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->driver->showDomain('unknown-id'));
    }

    public function test_list_domains_filters_by_website_id(): void
    {
        $filter = (new DomainFilter)->setFilterData([
            'website_id' => '01J00000000000000000000001',
        ]);

        $domains = $this->driver->listDomains(domainFilter: $filter);

        $this->assertCount(2, $domains);
        $this->assertSame('blog.example.com', $domains[0]->getData()['domain']);
        $this->assertSame('www.blog.example.com', $domains[1]->getData()['domain']);
    }

    public function test_list_domains_filters_by_domain(): void
    {
        $filter = (new DomainFilter)->setFilterData([
            'domain' => 'shop.demo.test',
        ]);

        $domains = $this->driver->listDomains(domainFilter: $filter);

        $this->assertCount(1, $domains);
        $this->assertSame('01J00000000000000000000002', $domains[0]->getData()['website_id']);
    }

    public function test_list_domains_supports_pagination(): void
    {
        $pageOne = $this->driver->listDomains(page: 1, limit: 1);
        $pageTwo = $this->driver->listDomains(page: 2, limit: 1);

        $this->assertCount(1, $pageOne);
        $this->assertCount(1, $pageTwo);
        $this->assertNotSame(
            $pageOne[0]->getData()['id'],
            $pageTwo[0]->getData()['id']
        );
    }

    public function test_it_seeds_sample_themes(): void
    {
        $themes = $this->driver->listThemes();

        $this->assertCount(3, $themes);
        $this->assertSame('Good News', $themes[0]->getData()['name']);
        $this->assertSame('goodnews', $themes[0]->getData()['key']);
        $this->assertSame('Storefront', $themes[1]->getData()['name']);
        $this->assertTrue($themes[1]->getData()['dev_mode']);
    }

    public function test_show_theme_returns_matching_resource(): void
    {
        $theme = $this->driver->showTheme('01J00000000000000000000081');

        $this->assertNotNull($theme);
        $this->assertSame('Good News', $theme->getData()['name']);
        $this->assertSame('goodnews', $theme->getData()['key']);
        $this->assertSame(1, $theme->getData()['websites_count']);
        $this->assertStringContainsString('main, footer', $theme->getData()['guidelines']);
    }

    public function test_show_theme_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->driver->showTheme('unknown-id'));
    }

    public function test_list_themes_supports_pagination(): void
    {
        $pageOne = $this->driver->listThemes(page: 1, limit: 1);
        $pageTwo = $this->driver->listThemes(page: 2, limit: 1);

        $this->assertCount(1, $pageOne);
        $this->assertCount(1, $pageTwo);
        $this->assertNotSame(
            $pageOne[0]->getData()['id'],
            $pageTwo[0]->getData()['id']
        );
    }

    public function test_list_themes_filters_by_search(): void
    {
        $filter = (new ThemeFilter)->setFilterData([
            'search' => 'store',
        ]);

        $themes = $this->driver->listThemes(themeFilter: $filter);

        $this->assertCount(1, $themes);
        $this->assertSame('Storefront', $themes[0]->getData()['name']);
    }

    public function test_list_themes_filters_by_ids(): void
    {
        $filter = (new ThemeFilter)->setFilterData([
            'ids' => '01J00000000000000000000081,01J00000000000000000000083',
        ]);

        $themes = $this->driver->listThemes(themeFilter: $filter);

        $this->assertCount(2, $themes);
        $this->assertSame('Good News', $themes[0]->getData()['name']);
        $this->assertSame('Minimal', $themes[1]->getData()['name']);
    }

    public function test_it_seeds_sample_menus(): void
    {
        $menus = $this->driver->listMenus('01J00000000000000000000001');

        $this->assertCount(2, $menus);
        $this->assertSame('main', $menus[0]->getData()['key']);
        $this->assertSame('footer', $menus[1]->getData()['key']);
        $this->assertCount(1, $this->driver->listMenus('01J00000000000000000000002'));
    }

    public function test_show_menu_returns_matching_resource(): void
    {
        $menu = $this->driver->showMenu('01J00000000000000000000011');

        $this->assertNotNull($menu);
        $this->assertSame('01J00000000000000000000001', $menu->getData()['website_id']);
        $this->assertSame('main', $menu->getData()['key']);
        $this->assertSame('Home', $menu->getData()['items'][0]['text']);
        $this->assertSame('/', $menu->getData()['items'][0]['link']);
    }

    public function test_show_menu_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->driver->showMenu('unknown-id'));
    }

    public function test_create_menu_persists_in_memory(): void
    {
        $createMenuData = (new CreateMenuData)->setData([
            'website_id' => '01J00000000000000000000001',
            'key' => 'sidebar',
            'items' => [
                [
                    'text' => 'Contact',
                    'link' => '/contact',
                    'new_tab' => 0,
                ],
            ],
        ]);

        $created = $this->driver->createMenu($createMenuData);
        $data = $created->getData();

        $this->assertSame('sidebar', $data['key']);
        $this->assertSame('01J00000000000000000000001', $data['website_id']);
        $this->assertSame('Contact', $data['items'][0]['text']);
        $this->assertNotEmpty($data['id']);
        $this->assertNotNull($this->driver->showMenu($data['id']));
        $this->assertCount(3, $this->driver->listMenus('01J00000000000000000000001'));
    }

    public function test_update_menu_merges_changes(): void
    {
        $updateMenuData = (new UpdateMenuData)->setData([
            'key' => 'primary',
            'items' => [
                [
                    'text' => 'Blog',
                    'link' => '/blog',
                    'new_tab' => 1,
                ],
            ],
        ]);

        $updated = $this->driver->updateMenu('01J00000000000000000000011', $updateMenuData);
        $data = $updated->getData();

        $this->assertSame('primary', $data['key']);
        $this->assertSame('Blog', $data['items'][0]['text']);
        $this->assertSame(1, $data['items'][0]['new_tab']);
        $this->assertSame('primary', $this->driver->showMenu('01J00000000000000000000011')?->getData()['key']);
    }

    public function test_update_menu_throws_for_unknown_id(): void
    {
        $updateMenuData = (new UpdateMenuData)->setData([
            'key' => 'main',
            'items' => [
                [
                    'text' => 'Home',
                    'link' => '/',
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Menu [missing-id] not found.');

        $this->driver->updateMenu('missing-id', $updateMenuData);
    }

    public function test_delete_menu_removes_record(): void
    {
        $this->assertTrue($this->driver->deleteMenu('01J00000000000000000000011'));
        $this->assertNull($this->driver->showMenu('01J00000000000000000000011'));
        $this->assertCount(1, $this->driver->listMenus('01J00000000000000000000001'));
    }

    public function test_delete_menu_returns_false_for_unknown_id(): void
    {
        $this->assertFalse($this->driver->deleteMenu('missing-id'));
    }

    public function test_list_menus_filters_by_website_id(): void
    {
        $menus = $this->driver->listMenus('01J00000000000000000000002');

        $this->assertCount(1, $menus);
        $this->assertSame('main', $menus[0]->getData()['key']);
        $this->assertSame('Shop', $menus[0]->getData()['items'][0]['text']);
    }

    public function test_list_menus_returns_empty_for_unknown_website(): void
    {
        $this->assertSame([], $this->driver->listMenus('unknown-website-id'));
    }

    public function test_it_seeds_sample_tags(): void
    {
        $tags = $this->driver->listTags('01J00000000000000000000001');

        $this->assertCount(2, $tags);
        $this->assertSame('Technology', $tags[0]->getData()['name']);
        $this->assertTrue($tags[0]->getData()['is_featured']);
        $this->assertSame('01J00000000000000000000071', $tags[0]->getData()['thumbnail_file_id']);
        $this->assertSame('hero-banner', $tags[0]->getData()['thumbnailFile']['code']);
        $this->assertSame('Lifestyle', $tags[1]->getData()['name']);
        $this->assertNull($tags[1]->getData()['thumbnail_file_id']);
        $this->assertNull($tags[1]->getData()['thumbnailFile']);
        $this->assertCount(1, $this->driver->listTags('01J00000000000000000000002'));
    }

    public function test_show_tag_returns_matching_resource(): void
    {
        $tag = $this->driver->showTag('01J00000000000000000000021');

        $this->assertNotNull($tag);
        $this->assertSame('01J00000000000000000000001', $tag->getData()['website_id']);
        $this->assertSame('Technology', $tag->getData()['name']);
        $this->assertSame('technology', $tag->getData()['slug']);
        $this->assertSame(12, $tag->getData()['public_posts_count']);
        $this->assertSame('01J00000000000000000000071', $tag->getData()['thumbnail_file_id']);
        $this->assertSame('hero-banner', $tag->getData()['thumbnailFile']['code']);
        $this->assertSame('uploads/hero-banner.jpg', $tag->getData()['thumbnailFile']['key']);
    }

    public function test_show_tag_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->driver->showTag('unknown-id'));
    }

    public function test_create_tag_persists_in_memory(): void
    {
        $createTagData = (new CreateTagData)->setData([
            'website_id' => '01J00000000000000000000001',
            'thumbnail_file_id' => '01J00000000000000000000072',
            'name' => 'Travel',
            'slug' => 'travel',
            'is_featured' => true,
            'description' => 'Travel stories',
        ]);

        $created = $this->driver->createTag($createTagData);
        $data = $created->getData();

        $this->assertSame('Travel', $data['name']);
        $this->assertSame('travel', $data['slug']);
        $this->assertTrue($data['is_featured']);
        $this->assertSame('01J00000000000000000000001', $data['website_id']);
        $this->assertSame('01J00000000000000000000072', $data['thumbnail_file_id']);
        $this->assertSame('uploads/weekend-ideas.webp', $data['thumbnailFile']['key']);
        $this->assertSame(0, $data['public_posts_count']);
        $this->assertNotEmpty($data['id']);
        $this->assertNotNull($this->driver->showTag($data['id']));
        $this->assertCount(3, $this->driver->listTags('01J00000000000000000000001'));
    }

    public function test_update_tag_merges_changes(): void
    {
        $updateTagData = (new UpdateTagData)->setData([
            'name' => 'Tech',
            'slug' => 'tech',
            'is_featured' => false,
            'description' => 'Updated description',
            'thumbnail_file_id' => '01J00000000000000000000072',
        ]);

        $updated = $this->driver->updateTag('01J00000000000000000000021', $updateTagData);
        $data = $updated->getData();

        $this->assertSame('Tech', $data['name']);
        $this->assertSame('tech', $data['slug']);
        $this->assertFalse($data['is_featured']);
        $this->assertSame('Updated description', $data['description']);
        $this->assertSame('01J00000000000000000000072', $data['thumbnail_file_id']);
        $this->assertSame('uploads/weekend-ideas.webp', $data['thumbnailFile']['key']);
        $this->assertSame('01J00000000000000000000001', $data['website_id']);
        $this->assertSame('Tech', $this->driver->showTag('01J00000000000000000000021')?->getData()['name']);
    }

    public function test_update_tag_ignores_website_id(): void
    {
        $updateTagData = (new UpdateTagData)->setData([
            'website_id' => '01J00000000000000000000002',
            'name' => 'Still Technology',
        ]);

        $updated = $this->driver->updateTag('01J00000000000000000000021', $updateTagData);

        $this->assertSame('01J00000000000000000000001', $updated->getData()['website_id']);
    }

    public function test_update_tag_throws_for_unknown_id(): void
    {
        $updateTagData = (new UpdateTagData)->setData([
            'name' => 'Missing Tag',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tag [missing-id] not found.');

        $this->driver->updateTag('missing-id', $updateTagData);
    }

    public function test_delete_tag_removes_record(): void
    {
        $this->assertTrue($this->driver->deleteTag('01J00000000000000000000021'));
        $this->assertNull($this->driver->showTag('01J00000000000000000000021'));
        $this->assertCount(1, $this->driver->listTags('01J00000000000000000000001'));
    }

    public function test_delete_tag_returns_false_for_unknown_id(): void
    {
        $this->assertFalse($this->driver->deleteTag('missing-id'));
    }

    public function test_list_tags_filters_by_name(): void
    {
        $filter = (new TagFilter)->setFilterData([
            'name' => 'life',
        ]);

        $tags = $this->driver->listTags('01J00000000000000000000001', tagFilter: $filter);

        $this->assertCount(1, $tags);
        $this->assertSame('Lifestyle', $tags[0]->getData()['name']);
    }

    public function test_list_tags_supports_pagination(): void
    {
        $pageOne = $this->driver->listTags('01J00000000000000000000001', page: 1, limit: 1);
        $pageTwo = $this->driver->listTags('01J00000000000000000000001', page: 2, limit: 1);

        $this->assertCount(1, $pageOne);
        $this->assertCount(1, $pageTwo);
        $this->assertNotSame(
            $pageOne[0]->getData()['id'],
            $pageTwo[0]->getData()['id']
        );
    }

    public function test_list_tags_returns_empty_for_unknown_website(): void
    {
        $this->assertSame([], $this->driver->listTags('unknown-website-id'));
    }

    public function test_it_seeds_sample_pages(): void
    {
        $pages = $this->driver->listPages('01J00000000000000000000001');

        $this->assertCount(2, $pages);
        $this->assertSame('about', $pages[0]->getData()['slug']);
        $this->assertSame('About Us', $pages[0]->getData()['title']);
        $this->assertSame('contact', $pages[1]->getData()['slug']);
        $this->assertCount(1, $this->driver->listPages('01J00000000000000000000002'));
    }

    public function test_show_page_returns_matching_resource(): void
    {
        $page = $this->driver->showPage('01J00000000000000000000041');

        $this->assertNotNull($page);
        $this->assertSame('01J00000000000000000000001', $page->getData()['website_id']);
        $this->assertSame('about', $page->getData()['slug']);
        $this->assertSame('About Us', $page->getData()['title']);
        $this->assertSame('About Us | Sample Blog', $page->getData()['seo_title']);
    }

    public function test_show_page_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->driver->showPage('unknown-id'));
    }

    public function test_create_page_persists_in_memory(): void
    {
        $createPageData = (new CreatePageData)->setData([
            'website_id' => '01J00000000000000000000001',
            'slug' => 'faq',
            'title' => 'FAQ',
            'seo_title' => 'FAQ | Sample Blog',
            'content' => '<p>Frequently asked questions.</p>',
        ]);

        $created = $this->driver->createPage($createPageData);
        $data = $created->getData();

        $this->assertSame('faq', $data['slug']);
        $this->assertSame('FAQ', $data['title']);
        $this->assertSame('01J00000000000000000000001', $data['website_id']);
        $this->assertNotEmpty($data['id']);
        $this->assertNotNull($this->driver->showPage($data['id']));
        $this->assertCount(3, $this->driver->listPages('01J00000000000000000000001'));
    }

    public function test_update_page_merges_changes(): void
    {
        $updatePageData = (new UpdatePageData)->setData([
            'title' => 'About Our Team',
            'slug' => 'our-team',
            'seo_description' => 'Meet the team behind Sample Blog.',
        ]);

        $updated = $this->driver->updatePage('01J00000000000000000000041', $updatePageData);
        $data = $updated->getData();

        $this->assertSame('our-team', $data['slug']);
        $this->assertSame('About Our Team', $data['title']);
        $this->assertSame('Meet the team behind Sample Blog.', $data['seo_description']);
        $this->assertSame('About Us | Sample Blog', $data['seo_title']);
        $this->assertSame('About Our Team', $this->driver->showPage('01J00000000000000000000041')?->getData()['title']);
    }

    public function test_update_page_throws_for_unknown_id(): void
    {
        $updatePageData = (new UpdatePageData)->setData([
            'title' => 'Missing Page',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page [missing-id] not found.');

        $this->driver->updatePage('missing-id', $updatePageData);
    }

    public function test_delete_page_removes_record(): void
    {
        $this->driver->deletePage('01J00000000000000000000041');

        $this->assertNull($this->driver->showPage('01J00000000000000000000041'));
        $this->assertCount(1, $this->driver->listPages('01J00000000000000000000001'));
    }

    public function test_delete_page_throws_for_unknown_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page [missing-id] not found.');

        $this->driver->deletePage('missing-id');
    }

    public function test_list_pages_supports_pagination(): void
    {
        $pageOne = $this->driver->listPages('01J00000000000000000000001', page: 1, limit: 1);
        $pageTwo = $this->driver->listPages('01J00000000000000000000001', page: 2, limit: 1);

        $this->assertCount(1, $pageOne);
        $this->assertCount(1, $pageTwo);
        $this->assertNotSame(
            $pageOne[0]->getData()['id'],
            $pageTwo[0]->getData()['id']
        );
    }

    public function test_list_pages_returns_empty_for_unknown_website(): void
    {
        $this->assertSame([], $this->driver->listPages('unknown-website-id'));
    }

    public function test_it_seeds_sample_posts(): void
    {
        $posts = $this->driver->listPosts('01J00000000000000000000001');

        $this->assertCount(2, $posts);
        $this->assertSame('hello-world', $posts[0]->getData()['slug']);
        $this->assertSame('Hello World', $posts[0]->getData()['title']);
        $this->assertSame('Technology', $posts[0]->getData()['tags'][0]['name']);
        $this->assertSame('01J00000000000000000000071', $posts[0]->getData()['thumbnail_file_id']);
        $this->assertSame('hero-banner', $posts[0]->getData()['thumbnailFile']['code']);
        $this->assertSame('weekend-ideas', $posts[1]->getData()['slug']);
        $this->assertNull($posts[1]->getData()['thumbnail_file_id']);
        $this->assertNull($posts[1]->getData()['thumbnailFile']);
        $this->assertCount(1, $this->driver->listPosts('01J00000000000000000000002'));
    }

    public function test_show_post_returns_matching_resource(): void
    {
        $post = $this->driver->showPost('01J00000000000000000000051');

        $this->assertNotNull($post);
        $this->assertSame('01J00000000000000000000001', $post->getData()['website_id']);
        $this->assertSame('hello-world', $post->getData()['slug']);
        $this->assertSame('Hello World', $post->getData()['title']);
        $this->assertSame('public', $post->getData()['visibility']);
        $this->assertSame('Technology', $post->getData()['tags'][0]['name']);
        $this->assertSame('01J00000000000000000000071', $post->getData()['thumbnail_file_id']);
        $this->assertSame('uploads/hero-banner.jpg', $post->getData()['thumbnailFile']['key']);
    }

    public function test_show_post_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->driver->showPost('unknown-id'));
    }

    public function test_create_post_persists_in_memory(): void
    {
        $createPostData = (new CreatePostData)->setData([
            'website_id' => '01J00000000000000000000001',
            'slug' => 'draft-post',
            'title' => 'Draft Post',
            'description' => 'A draft article',
            'thumbnail_file_id' => '01J00000000000000000000072',
            'visibility' => 'public',
            'tag_ids' => ['01J00000000000000000000021'],
        ]);

        $created = $this->driver->createPost($createPostData);
        $data = $created->getData();

        $this->assertSame('draft-post', $data['slug']);
        $this->assertSame('Draft Post', $data['title']);
        $this->assertSame('01J00000000000000000000001', $data['website_id']);
        $this->assertSame('public', $data['visibility']);
        $this->assertSame('Technology', $data['tags'][0]['name']);
        $this->assertSame('01J00000000000000000000072', $data['thumbnail_file_id']);
        $this->assertSame('uploads/weekend-ideas.webp', $data['thumbnailFile']['key']);
        $this->assertNotEmpty($data['id']);
        $this->assertNotNull($data['published_at']);
        $this->assertNotNull($this->driver->showPost($data['id']));
        $this->assertCount(3, $this->driver->listPosts('01J00000000000000000000001'));
    }

    public function test_update_post_merges_changes(): void
    {
        $updatePostData = (new UpdatePostData)->setData([
            'id' => '01J00000000000000000000051',
            'visibility' => 'public',
            'title' => 'Hello Universe',
            'slug' => 'hello-universe',
            'description' => 'Updated description',
            'thumbnail_file_id' => '01J00000000000000000000072',
        ]);

        $updated = $this->driver->updatePost($updatePostData);
        $data = $updated->getData();

        $this->assertSame('hello-universe', $data['slug']);
        $this->assertSame('Hello Universe', $data['title']);
        $this->assertSame('Updated description', $data['description']);
        $this->assertSame('01J00000000000000000000072', $data['thumbnail_file_id']);
        $this->assertSame('uploads/weekend-ideas.webp', $data['thumbnailFile']['key']);
        $this->assertSame('Hello Universe', $this->driver->showPost('01J00000000000000000000051')?->getData()['title']);
    }

    public function test_update_post_updates_tag_ids(): void
    {
        $updatePostData = (new UpdatePostData)->setData([
            'id' => '01J00000000000000000000051',
            'visibility' => 'public',
            'tag_ids' => ['01J00000000000000000000022'],
        ]);

        $updated = $this->driver->updatePost($updatePostData);

        $this->assertSame('Lifestyle', $updated->getData()['tags'][0]['name']);
    }

    public function test_update_post_requires_id(): void
    {
        $updatePostData = (new UpdatePostData)->setData([
            'visibility' => 'public',
            'title' => 'Missing ID',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Post id is required for update.');

        $this->driver->updatePost($updatePostData);
    }

    public function test_update_post_throws_for_unknown_id(): void
    {
        $updatePostData = (new UpdatePostData)->setData([
            'id' => 'missing-id',
            'visibility' => 'public',
            'title' => 'Missing Post',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Post [missing-id] not found.');

        $this->driver->updatePost($updatePostData);
    }

    public function test_delete_post_removes_record(): void
    {
        $this->assertTrue($this->driver->deletePost('01J00000000000000000000051'));
        $this->assertNull($this->driver->showPost('01J00000000000000000000051'));
        $this->assertCount(1, $this->driver->listPosts('01J00000000000000000000001'));
    }

    public function test_delete_post_returns_false_for_unknown_id(): void
    {
        $this->assertFalse($this->driver->deletePost('missing-id'));
    }

    public function test_list_posts_filters_by_search(): void
    {
        $filter = (new PostFilter)->setFilterData([
            'search' => 'weekend',
        ]);

        $posts = $this->driver->listPosts('01J00000000000000000000001', postFilter: $filter);

        $this->assertCount(1, $posts);
        $this->assertSame('Weekend Ideas', $posts[0]->getData()['title']);
    }

    public function test_list_posts_filters_by_slug(): void
    {
        $filter = (new PostFilter)->setFilterData([
            'slug' => 'hello-world',
        ]);

        $posts = $this->driver->listPosts('01J00000000000000000000001', postFilter: $filter);

        $this->assertCount(1, $posts);
        $this->assertSame('Hello World', $posts[0]->getData()['title']);
    }

    public function test_list_posts_filters_by_visibility(): void
    {
        $filter = (new PostFilter)->setFilterData([
            'visibility' => 'private',
        ]);

        $posts = $this->driver->listPosts('01J00000000000000000000002', postFilter: $filter);

        $this->assertCount(1, $posts);
        $this->assertSame('New Arrivals', $posts[0]->getData()['title']);
    }

    public function test_list_posts_filters_by_tag_id(): void
    {
        $filter = (new PostFilter)->setFilterData([
            'tag_id' => '01J00000000000000000000022',
        ]);

        $posts = $this->driver->listPosts('01J00000000000000000000001', postFilter: $filter);

        $this->assertCount(1, $posts);
        $this->assertSame('weekend-ideas', $posts[0]->getData()['slug']);
    }

    public function test_list_posts_supports_pagination(): void
    {
        $pageOne = $this->driver->listPosts('01J00000000000000000000001', page: 1, limit: 1);
        $pageTwo = $this->driver->listPosts('01J00000000000000000000001', page: 2, limit: 1);

        $this->assertCount(1, $pageOne);
        $this->assertCount(1, $pageTwo);
        $this->assertNotSame(
            $pageOne[0]->getData()['id'],
            $pageTwo[0]->getData()['id']
        );
    }

    public function test_list_posts_orders_newer_first(): void
    {
        $posts = $this->driver->listPosts('01J00000000000000000000001', orderDirection: -1);

        $this->assertSame('weekend-ideas', $posts[0]->getData()['slug']);
        $this->assertSame('hello-world', $posts[1]->getData()['slug']);
    }

    public function test_list_posts_orders_older_first(): void
    {
        $posts = $this->driver->listPosts('01J00000000000000000000001', orderDirection: 1);

        $this->assertSame('hello-world', $posts[0]->getData()['slug']);
        $this->assertSame('weekend-ideas', $posts[1]->getData()['slug']);
    }

    public function test_list_posts_returns_empty_for_unknown_website(): void
    {
        $this->assertSame([], $this->driver->listPosts('unknown-website-id'));
    }

    public function test_it_seeds_sample_users(): void
    {
        $users = $this->driver->listUsers();

        $this->assertCount(2, $users);
        $this->assertSame('Alex Editor', $users[0]->getData()['name']);
        $this->assertSame('Sam Manager', $users[1]->getData()['name']);
    }

    public function test_show_user_returns_matching_resource(): void
    {
        $user = $this->driver->showUser('01J00000000000000000000061');

        $this->assertNotNull($user);
        $this->assertSame('Alex Editor', $user->getData()['name']);
        $this->assertSame('alex@example.com', $user->getData()['email']);
        $this->assertArrayNotHasKey('website_ids', $user->getData());
    }

    public function test_show_user_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->driver->showUser('unknown-id'));
    }

    public function test_create_user_persists_in_memory(): void
    {
        $createUserData = (new CreateUserData)->setData([
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'secret',
            'level' => 2,
        ]);

        $created = $this->driver->createUser($createUserData);
        $data = $created->getData();

        $this->assertSame('New User', $data['name']);
        $this->assertSame('new@example.com', $data['email']);
        $this->assertSame(2, $data['level']);
        $this->assertNotEmpty($data['id']);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertNotNull($this->driver->showUser($data['id']));
        $this->assertCount(3, $this->driver->listUsers());
    }

    public function test_update_user_merges_changes(): void
    {
        $updateUserData = (new UpdateUserData)->setData([
            'name' => 'Alex Updated',
            'level' => 3,
        ]);

        $updated = $this->driver->updateUser('01J00000000000000000000061', $updateUserData);

        $this->assertSame('Alex Updated', $updated->getData()['name']);
        $this->assertSame(3, $updated->getData()['level']);
        $this->assertSame('alex@example.com', $updated->getData()['email']);
    }

    public function test_update_user_throws_for_unknown_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User [unknown-id] not found.');

        $this->driver->updateUser('unknown-id', new UpdateUserData);
    }

    public function test_delete_user_removes_record(): void
    {
        $this->assertTrue($this->driver->deleteUser('01J00000000000000000000061'));
        $this->assertNull($this->driver->showUser('01J00000000000000000000061'));
        $this->assertCount(1, $this->driver->listUsers());
    }

    public function test_delete_user_returns_false_for_unknown_id(): void
    {
        $this->assertFalse($this->driver->deleteUser('unknown-id'));
    }

    public function test_list_users_filters_by_search(): void
    {
        $filter = (new UserFilter)->setFilterData([
            'search' => 'manager',
        ]);

        $users = $this->driver->listUsers(userFilter: $filter);

        $this->assertCount(1, $users);
        $this->assertSame('Sam Manager', $users[0]->getData()['name']);
    }

    public function test_list_users_filters_by_website_id(): void
    {
        $filter = (new UserFilter)->setFilterData([
            'website_id' => '01J00000000000000000000002',
        ]);

        $users = $this->driver->listUsers(userFilter: $filter);

        $this->assertCount(1, $users);
        $this->assertSame('Sam Manager', $users[0]->getData()['name']);
    }

    public function test_list_users_supports_pagination(): void
    {
        $pageOne = $this->driver->listUsers(page: 1, limit: 1);
        $pageTwo = $this->driver->listUsers(page: 2, limit: 1);

        $this->assertCount(1, $pageOne);
        $this->assertCount(1, $pageTwo);
        $this->assertNotSame(
            $pageOne[0]->getData()['id'],
            $pageTwo[0]->getData()['id']
        );
    }

    public function test_list_users_filters_by_role_id(): void
    {
        $filter = (new UserFilter)->setFilterData([
            'role_id' => '01J00000000000000000000092',
        ]);

        $users = $this->driver->listUsers(userFilter: $filter);

        $this->assertCount(1, $users);
        $this->assertSame('Sam Manager', $users[0]->getData()['name']);
    }

    public function test_it_seeds_sample_roles(): void
    {
        $roles = $this->driver->listRoles();

        $this->assertCount(2, $roles);
        $this->assertSame('Editor', $roles[0]->getData()['name']);
        $this->assertSame('Manager', $roles[1]->getData()['name']);
    }

    public function test_show_role_returns_matching_resource(): void
    {
        $role = $this->driver->showRole('01J00000000000000000000091');

        $this->assertNotNull($role);
        $this->assertSame('Editor', $role->getData()['name']);
        $this->assertSame(['posts.create', 'posts.update', 'posts.delete'], $role->getData()['abilities']);
    }

    public function test_show_role_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->driver->showRole('unknown-id'));
    }

    public function test_create_role_persists_in_memory(): void
    {
        $createRoleData = (new CreateRoleData)->setData([
            'name' => 'Contributor',
            'abilities' => ['posts.create'],
        ]);

        $created = $this->driver->createRole($createRoleData);
        $data = $created->getData();

        $this->assertSame('Contributor', $data['name']);
        $this->assertSame(['posts.create'], $data['abilities']);
        $this->assertNotEmpty($data['id']);
        $this->assertNotNull($this->driver->showRole($data['id']));
        $this->assertCount(3, $this->driver->listRoles());
    }

    public function test_update_role_merges_changes(): void
    {
        $updateRoleData = (new UpdateRoleData)->setData([
            'name' => 'Senior Editor',
            'abilities' => ['posts.create', 'posts.update', 'posts.delete', 'posts.publish'],
        ]);

        $updated = $this->driver->updateRole('01J00000000000000000000091', $updateRoleData);

        $this->assertSame('Senior Editor', $updated->getData()['name']);
        $this->assertSame(
            ['posts.create', 'posts.update', 'posts.delete', 'posts.publish'],
            $updated->getData()['abilities']
        );
    }

    public function test_update_role_throws_for_unknown_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Role [unknown-id] not found.');

        $this->driver->updateRole('unknown-id', new UpdateRoleData);
    }

    public function test_delete_role_removes_record(): void
    {
        $deleted = $this->driver->deleteRole('01J00000000000000000000091');

        $this->assertSame('Editor', $deleted->getData()['name']);
        $this->assertNull($this->driver->showRole('01J00000000000000000000091'));
        $this->assertCount(1, $this->driver->listRoles());
    }

    public function test_delete_role_throws_for_unknown_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Role [missing-id] not found.');

        $this->driver->deleteRole('missing-id');
    }

    public function test_list_roles_filters_by_search(): void
    {
        $filter = (new RoleFilter)->setFilterData([
            'search' => 'manager',
        ]);

        $roles = $this->driver->listRoles(roleFilter: $filter);

        $this->assertCount(1, $roles);
        $this->assertSame('Manager', $roles[0]->getData()['name']);
    }

    public function test_list_roles_supports_pagination(): void
    {
        $pageOne = $this->driver->listRoles(page: 1, perPage: 1);
        $pageTwo = $this->driver->listRoles(page: 2, perPage: 1);

        $this->assertCount(1, $pageOne);
        $this->assertCount(1, $pageTwo);
        $this->assertNotSame(
            $pageOne[0]->getData()['id'],
            $pageTwo[0]->getData()['id']
        );
    }

    public function test_it_seeds_sample_files(): void
    {
        $files = $this->driver->listFiles();

        $this->assertCount(3, $files);
        $this->assertSame('hero-banner', $files[0]->getData()['code']);
        $this->assertSame('image', $files[0]->getData()['type']);
    }

    public function test_show_file_returns_matching_resource(): void
    {
        $file = $this->driver->showFile('01J00000000000000000000071');

        $this->assertNotNull($file);
        $this->assertSame('hero-banner', $file->getData()['code']);
        $this->assertSame('uploads/hero-banner.jpg', $file->getData()['key']);
        $this->assertTrue($file->getData()['is_uploaded']);
    }

    public function test_show_file_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->driver->showFile('unknown-id'));
    }

    public function test_create_file_persists_in_memory(): void
    {
        $createFileData = (new CreateFileData)->setData([
            'ext' => 'png',
            'filename' => 'new-asset',
            'code' => 'new-asset-code',
            'information' => ['width' => 800],
        ]);

        $created = $this->driver->createFile('binary-content', $createFileData);
        $data = $created->getData();

        $this->assertSame('new-asset-code', $data['code']);
        $this->assertSame('uploads/new-asset.png', $data['key']);
        $this->assertSame('image', $data['type']);
        $this->assertSame('image/png', $data['mime']);
        $this->assertSame(14, $data['size']);
        $this->assertTrue($data['is_uploaded']);
        $this->assertNotEmpty($data['id']);
        $this->assertNotNull($this->driver->showFile($data['id']));
        $this->assertCount(4, $this->driver->listFiles());
    }

    public function test_update_file_merges_changes(): void
    {
        $updateFileData = (new UpdateFileData)->setData([
            'code' => 'updated-hero',
            'information' => ['alt' => 'Updated alt text'],
        ]);

        $updated = $this->driver->updateFile('01J00000000000000000000071', $updateFileData);

        $this->assertSame('updated-hero', $updated->getData()['code']);
        $this->assertSame(['alt' => 'Updated alt text'], $updated->getData()['information']);
        $this->assertSame('uploads/hero-banner.jpg', $updated->getData()['key']);
    }

    public function test_update_file_throws_for_unknown_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File [unknown-id] not found.');

        $this->driver->updateFile('unknown-id', new UpdateFileData);
    }

    public function test_delete_file_returns_resource_and_removes_record(): void
    {
        $deleted = $this->driver->deleteFile('01J00000000000000000000071');

        $this->assertTrue($deleted);
        $this->assertNull($this->driver->showFile('01J00000000000000000000071'));
        $this->assertCount(2, $this->driver->listFiles());
    }

    public function test_delete_file_throws_for_unknown_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File [missing-id] not found.');

        $this->driver->deleteFile('missing-id');
    }

    public function test_list_files_filters_by_user_id(): void
    {
        $filter = (new FileFilter)->setFilterData([
            'user_id' => '01J00000000000000000000061',
        ]);

        $files = $this->driver->listFiles(fileFilter: $filter);

        $this->assertCount(1, $files);
        $this->assertSame('hero-banner', $files[0]->getData()['code']);
    }

    public function test_create_file_sets_authenticated_user_id(): void
    {
        $this->driver->updateUser('01J00000000000000000000062', (new UpdateUserData)->setData([
            'api_key' => 'user-api-key-62',
        ]));

        $this->driver->setConfig(new Config([
            'base_url' => 'https://flycms.example.test',
            'api_key' => 'user-api-key-62',
        ]));

        $created = $this->driver->createFile(
            'binary-content',
            (new CreateFileData)->setData([
                'ext' => 'png',
                'filename' => 'owned-asset',
            ])
        );

        $this->assertSame('01J00000000000000000000062', $created->getData()['user_id']);
    }

    public function test_list_files_filters_by_post_id(): void
    {
        $filter = (new FileFilter)->setFilterData([
            'post_id' => '01J00000000000000000000051',
        ]);

        $files = $this->driver->listFiles(fileFilter: $filter);

        $this->assertCount(1, $files);
        $this->assertSame('hero-banner', $files[0]->getData()['code']);
    }

    public function test_list_files_filters_by_code(): void
    {
        $filter = (new FileFilter)->setFilterData([
            'code' => 'storefront-intro',
        ]);

        $files = $this->driver->listFiles(fileFilter: $filter);

        $this->assertCount(1, $files);
        $this->assertSame('video', $files[0]->getData()['type']);
    }

    public function test_list_files_filters_by_type(): void
    {
        $filter = (new FileFilter)->setFilterData([
            'type' => 'image',
        ]);

        $files = $this->driver->listFiles(fileFilter: $filter);

        $this->assertCount(2, $files);
    }

    public function test_list_files_supports_pagination(): void
    {
        $pageOne = $this->driver->listFiles(page: 1, limit: 1);
        $pageTwo = $this->driver->listFiles(page: 2, limit: 1);

        $this->assertCount(1, $pageOne);
        $this->assertCount(1, $pageTwo);
        $this->assertNotSame(
            $pageOne[0]->getData()['id'],
            $pageTwo[0]->getData()['id']
        );
    }

    public function test_list_files_orders_newer_first(): void
    {
        $files = $this->driver->listFiles(orderDirection: -1);

        $this->assertSame('01J00000000000000000000073', $files[0]->getData()['id']);
        $this->assertSame('01J00000000000000000000072', $files[1]->getData()['id']);
        $this->assertSame('01J00000000000000000000071', $files[2]->getData()['id']);
    }
}
