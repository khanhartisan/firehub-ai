<?php

namespace Tests\Unit\Services\PlatformManager\FlyCms\Drivers;

use App\Contracts\PlatformManager\FlyCms\Config;
use App\Contracts\PlatformManager\FlyCms\Filters\DomainFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\PostFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\TagFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\WebsiteFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\CreatePostData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\UpdatePostData;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\CreateMenuData;
use App\Contracts\PlatformManager\FlyCms\MutationData\MenuMutationData\UpdateMenuData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\CreatePageData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PageMutationData\UpdatePageData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\CreateTagData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\UpdateTagData;
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
        ]);

        $created = $this->driver->createWebsite($createWebsiteData);
        $data = $created->getData();

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
        $data = $updated->getData();

        $this->assertSame('Renamed Blog', $data['name']);
        $this->assertSame('inactive', $data['status']);
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
        $this->assertSame('Lifestyle', $tags[1]->getData()['name']);
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
    }

    public function test_show_tag_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->driver->showTag('unknown-id'));
    }

    public function test_create_tag_persists_in_memory(): void
    {
        $createTagData = (new CreateTagData)->setData([
            'website_id' => '01J00000000000000000000001',
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
        ]);

        $updated = $this->driver->updateTag('01J00000000000000000000021', $updateTagData);
        $data = $updated->getData();

        $this->assertSame('Tech', $data['name']);
        $this->assertSame('tech', $data['slug']);
        $this->assertFalse($data['is_featured']);
        $this->assertSame('Updated description', $data['description']);
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
        $this->assertSame('weekend-ideas', $posts[1]->getData()['slug']);
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
        ]);

        $updated = $this->driver->updatePost($updatePostData);
        $data = $updated->getData();

        $this->assertSame('hello-universe', $data['slug']);
        $this->assertSame('Hello Universe', $data['title']);
        $this->assertSame('Updated description', $data['description']);
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
}
