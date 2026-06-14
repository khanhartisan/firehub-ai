<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\PublishingResult;
use App\Models\Publication;

trait InteractsWithArticles
{
    public function publishArticle(Publication $publication): PublishingResult
    {
        // TODO: implement FlyCMS article publishing for the real API driver.
        throw new \BadMethodCallException('FlyCmsDriver::publishArticle is not implemented yet.');
    }
}
