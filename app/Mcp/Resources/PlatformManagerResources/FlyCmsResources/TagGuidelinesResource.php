<?php

namespace App\Mcp\Resources\PlatformManagerResources\FlyCmsResources;

use App\Contracts\PlatformManager\FlyCms\Guidelines\TagFlyCmsGuidelines;
use App\Mcp\Resources\OverviewResource;
use App\Mcp\Support\PlatformManager\FlyCms\FlyCmsGuidelinesRenderer;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Title('FlyCMS Tag Guidelines')]
#[Description('Editorial and formatting rules for FlyCMS tag fields: identity, SEO liquid templates, and HTML content.')]
#[Uri('file://resources/tag-guidelines-resource')]
#[MimeType('text/markdown')]
class TagGuidelinesResource extends FlyCmsResource
{
    /**
     * @return list<class-string<\App\Mcp\Resources\Resource>>
     */
    protected static function breadcrumbParents(): array
    {
        return [
            OverviewResource::class,
            FlyCmsOverviewResource::class,
        ];
    }

    public function handle(Request $request): Response
    {
        return Response::text(FlyCmsGuidelinesRenderer::render(
            TagFlyCmsGuidelines::class,
            self::class,
            static::breadcrumbParents(),
        ));
    }
}
