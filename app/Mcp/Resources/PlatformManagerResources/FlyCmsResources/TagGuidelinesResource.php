<?php

namespace App\Mcp\Resources\PlatformManagerResources\FlyCmsResources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Guidelines for FlyCms Tags')]
class TagGuidelinesResource extends FlyCmsResource
{
    /**
     * Handle the resource request.
     */
    public function handle(Request $request): Response
    {
        // TODO: Implement

        return Response::text('The resource content.');
    }
}
