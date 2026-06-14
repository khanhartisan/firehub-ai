<?php

namespace App\Contracts\PlatformManager;

use App\Models\Publication;

interface ArticlePlatformManager
{
    /**
     * @param Publication $publication The publication whose publishable is an Article
     * @return PublishingResult
     */
    public function publishArticle(Publication $publication): PublishingResult;
}