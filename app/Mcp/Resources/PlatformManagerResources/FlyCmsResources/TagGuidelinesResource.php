<?php

namespace App\Mcp\Resources\PlatformManagerResources\FlyCmsResources;

use App\Mcp\Resources\GuidelineResource;
use App\Contracts\PlatformManager\FlyCms\Guidelines\TagFlyCmsGuidelines;
use App\Mcp\Resources\OverviewResource as AppOverviewResource;
use App\Mcp\Resources\PublishingChannelsOverviewResource;
use App\Mcp\Support\PlatformManager\FlyCms\FlyCmsGuidelinesRenderer;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;

#[Title('FlyCMS Tag Guidelines')]
#[Description('Editorial and formatting rules for FlyCMS tag fields: identity, SEO liquid templates, and liquid content.')]
#[Uri('platform-manager://flycms/tag-guidelines')]
#[MimeType('text/markdown')]
class TagGuidelinesResource extends FlyCmsResource implements GuidelineResource
{
    /**
     * @return list<class-string<\App\Mcp\Resources\Resource>>
     */
    protected static function breadcrumbParents(): array
    {
        return [
            AppOverviewResource::class,
            PublishingChannelsOverviewResource::class,
            OverviewResource::class,
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
