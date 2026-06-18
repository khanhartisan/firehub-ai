<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\FileFilter;
use App\Contracts\PlatformManager\FlyCms\Resources\FileResource;
use App\Contracts\PlatformManager\PublishingResult;
use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\File;
use App\Models\Publication;
use Exception;

trait InteractsWithArticles
{
    /**
     * @throws Exception
     */
    public function publishArticle(Publication $publication): PublishingResult
    {
        $article = $publication->publishable;
        if (!$article instanceof Article) {
            throw new \InvalidArgumentException('The publishable resource is not an instance of Article.');
        }

        // TODO: Implement this
        return new PublishingResult(
            PublicationStatus::ERROR,
            null,
            'Not implemented'
        );
    }


    /**
     * @throws FlyCmsException
     */
    protected function findFlyCmsFileByCode(string $code): ?FileResource
    {
        if ($code === '') {
            return null;
        }

        $files = $this->listFiles(
            1,
            1,
            null,
            (new FileFilter)->setFilterData([
                'code' => $code,
            ]),
        );

        if ($files === []) {
            return null;
        }

        /** @var FileResource */
        return $files[0];
    }

    protected function resolveThumbnailFileExt(File $thumbnailFile): string
    {
        $ext = strtolower((string) ($thumbnailFile->extension ?? ''));

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return $ext;
        }

        return 'jpg';
    }


    protected function resolveContentLang(Article $article): string
    {
        return $article->language?->value ?: 'default';
    }

    protected function articleContentHtml(Article $article): ?string
    {
        $content = $article->illustrated_article?->toHtml()
            ?: $article->article?->toHtml();

        if (! is_string($content) || $content === '') {
            return null;
        }

        return $content;
    }
}
