<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Config;
use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\ContentFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\FileFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\PartFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\SubjectFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\TagFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\ThumbnailFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\ContentMutationData\CreateContentData;
use App\Contracts\PlatformManager\FlyCms\MutationData\ContentMutationData\UpdateContentData;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\CreateFileData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PartMutationData\CreatePartData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\UpdatePostData;
use App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData\CreateTagData;
use App\Contracts\PlatformManager\FlyCms\MutationData\SubjectMutationData\CreateSubjectData;
use App\Contracts\PlatformManager\FlyCms\MutationData\ThumbnailMutationData\CreateThumbnailData;
use App\Contracts\PlatformManager\FlyCms\Resources\ContentResource;
use App\Contracts\PlatformManager\FlyCms\Resources\FileResource;
use App\Contracts\PlatformManager\FlyCms\Resources\PartResource;
use App\Contracts\PlatformManager\FlyCms\Resources\PostResource;
use App\Contracts\PlatformManager\FlyCms\Resources\SubjectResource;
use App\Contracts\PlatformManager\FlyCms\Resources\TagResource;
use App\Contracts\PlatformManager\FlyCms\Resources\ThumbnailResource;
use App\Contracts\PlatformManager\PublishingResult;
use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\File;
use App\Models\Publication;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

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

    /**
     * @throws FlyCmsException
     */
    protected function uploadThumbnailFile(File $thumbnailFile,
                                           ThumbnailResource $thumbnailResource): FileResource
    {
        if (!$thumbnailId = $thumbnailResource->get('id') or !is_string($thumbnailId)) {
            throw new FlyCmsException('Thumbnail id is required.');
        }

        if (! is_string($thumbnailFile->path) || $thumbnailFile->path === ''
            || ! Storage::exists($thumbnailFile->path)
        ) {
            throw new FlyCmsException('Thumbnail file is not available for upload.');
        }

        $content = Storage::get($thumbnailFile->path);

        if (! is_string($content) || $content === '') {
            throw new FlyCmsException('Thumbnail file content is empty.');
        }

        $ext = $this->resolveThumbnailFileExt($thumbnailFile);

        $fileResource = $this->createFile(
            $content,
            (new CreateFileData)->setData([
                'ext' => $ext,
                'code' => $thumbnailFile->id,
                'filename' => 'thumbnail-'.$thumbnailFile->id,
            ]),
        );

        $fileId = $fileResource->get('id');

        if (! is_string($fileId) || $fileId === '') {
            throw new FlyCmsException('Failed to create thumbnail file.');
        }

        $this->attachFile($fileId, 'thumbnail', $thumbnailId);

        return $fileResource;
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
