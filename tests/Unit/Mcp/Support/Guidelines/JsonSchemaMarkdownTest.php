<?php

namespace Tests\Unit\Mcp\Support\Guidelines;

use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\CreateTagData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\UpdateTagData;
use App\Contracts\PlatformManager\FlyCms\Resources\TagResource;
use App\Contracts\PlatformManager\FlyCms\Resources\WebsiteResource;
use App\Mcp\Support\Guidelines\JsonSchemaMarkdown;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use PHPUnit\Framework\TestCase;

class JsonSchemaMarkdownTest extends TestCase
{
    public function test_mutation_field_table_includes_create_and_update_fields(): void
    {
        $markdown = JsonSchemaMarkdown::mutationFieldTable(
            CreateTagData::class,
            UpdateTagData::class,
            ['website_id'],
        );

        $this->assertStringContainsString('## Fields overview', $markdown);
        $this->assertStringContainsString('`name`', $markdown);
        $this->assertStringContainsString('`slug`', $markdown);
        $this->assertStringContainsString('`seo_title`', $markdown);
        $this->assertStringContainsString('Liquid template', $markdown);
        $this->assertStringContainsString('HTML', $markdown);
        $this->assertStringNotContainsString('`website_id`', $markdown);
        $this->assertStringNotContainsString('See resource:', $markdown);
    }

    public function test_mutation_field_table_marks_immutable_name_as_unavailable_on_update(): void
    {
        $markdown = JsonSchemaMarkdown::mutationFieldTable(
            CreateTagData::class,
            UpdateTagData::class,
            ['website_id'],
        );

        $this->assertMatchesRegularExpression(
            '/\| `name` \| Plain text \| Yes \| No \| Tag name \|/',
            $markdown,
        );
    }

    public function test_mutation_field_table_includes_constraints_in_notes(): void
    {
        $markdown = JsonSchemaMarkdown::mutationFieldTable(
            CreateTagData::class,
            UpdateTagData::class,
            ['website_id'],
        );

        $this->assertStringContainsString('max 255 characters', $markdown);
        $this->assertStringContainsString('default false', $markdown);
    }

    public function test_resource_output_table_lists_tag_response_fields(): void
    {
        $markdown = JsonSchemaMarkdown::resourceOutputTable(
            TagResource::class,
            ['thumbnailFile'],
        );

        $this->assertStringContainsString('## Response fields', $markdown);
        $this->assertStringContainsString('`public_posts_count`', $markdown);
        $this->assertStringContainsString('cannot be changed after creation', $markdown);
        $this->assertStringNotContainsString('`thumbnailFile`', $markdown);
    }

    public function test_meta_fallback_table_lists_requested_website_meta_keys(): void
    {
        $factory = new JsonSchemaTypeFactory;

        $markdown = JsonSchemaMarkdown::metaFallbackTable(
            WebsiteResource::getMetaSchema($factory),
            ['tag-seo-title', 'tag-seo-description'],
            intro: 'When tag SEO fields are null, use these website meta defaults:',
        );

        $this->assertStringContainsString('## Website meta fallbacks', $markdown);
        $this->assertStringContainsString('When tag SEO fields are null', $markdown);
        $this->assertStringContainsString('`tag-seo-title`', $markdown);
        $this->assertStringContainsString('`tag-seo-description`', $markdown);
        $this->assertStringNotContainsString('`home-seo-title`', $markdown);
    }
}
