<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Config;
use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\FileFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\CreateFileData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\CreatePostData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\UpdatePostData;
use App\Contracts\PlatformManager\FlyCms\Resources\FileResource;
use App\Contracts\PlatformManager\PublishingResult;
use App\Enums\ArticleStatus;
use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\File;
use App\Models\Publication;
use App\Utils\Str;
use Illuminate\Support\Facades\Storage;

trait InteractsWithArticles
{
    public function publishArticle(Publication $publication): PublishingResult
    {
        $publication->loadMissing(['channel', 'publishable']);

        $article = $publication->publishable;

        if (! $article instanceof Article) {
            return new PublishingResult(PublicationStatus::ERROR);
        }

        $article->loadMissing(['thumbnailFile', 'tags']);

        if ($article->status !== ArticleStatus::READY) {
            return new PublishingResult(PublicationStatus::AWAITING);
        }

        $websiteId = $publication->channel?->reference;

        if (! is_string($websiteId) || $websiteId === '') {
            return new PublishingResult(PublicationStatus::FAILED, null, 'Website id was not provided.');
        }

        $config = $this->getConfig();

        if (! $config instanceof Config) {
            return new PublishingResult(PublicationStatus::FAILED, null, 'Invalid configuration.');
        }

        $branchId = $config->getBranchId();

        if (! is_string($branchId) || $branchId === '') {
            return new PublishingResult(PublicationStatus::FAILED, null, 'Branch id was not provided.');
        }

        $contentHtml = $this->articleContentHtml($article);

        if (! is_string($contentHtml) || $contentHtml === '') {
            return new PublishingResult(PublicationStatus::ERROR, null, 'Article content is empty.');
        }

        $isUpdate = is_string($publication->reference) && $publication->reference !== '';

        try {
            $payload = $this->postPayloadFromPublication(
                $publication,
                $article,
                $contentHtml,
                $isUpdate,
            );

            $post = $isUpdate
                ? $this->updatePost((new UpdatePostData)->setData([
                    'id' => $publication->reference,
                    ...$payload,
                ]))
                : $this->createPost((new CreatePostData)->setData([
                    'branch_id' => $branchId,
                    'code' => $article->id,
                    'website_id' => $websiteId,
                    'slug' => $this->resolvePostSlug($article, $publication),
                    ...$payload,
                ]));

            $postId = $post->get('id');

            if (! is_string($postId) || $postId === '') {
                return new PublishingResult(PublicationStatus::ERROR);
            }

            return new PublishingResult(PublicationStatus::PUBLISHED, $postId);
        } catch (FlyCmsException $exception) {
            return new PublishingResult(PublicationStatus::ERROR, null, $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FlyCmsException
     */
    protected function postPayloadFromPublication(Publication $publication,
                                                  Article $article,
                                                  string $contentHtml,
                                                  bool $isUpdate): array
    {
        $meta = is_array($publication->meta) ? $publication->meta : [];

        $title = $publication->title ?? $article->title;
        $description = $publication->description ?? $article->excerpt;
        $lang = $this->resolveContentLang($article);

        $payload = array_filter([
            'title' => $title,
            'description' => $description,
            'lang' => $lang,
            'seo_title' => $meta['seo_title'] ?? $title,
            'seo_description' => $meta['seo_description'] ?? $description,
            'visibility' => $meta['visibility'] ?? 'public',
            'restriction' => $meta['restriction'] ?? 0,
            'thumbnail_file_id' => $this->resolveThumbnailFileId($article),
            'content' => array_filter([
                'lang' => $lang,
                'title' => $title,
                'description' => $description,
                'content' => $contentHtml,
            ], static fn (mixed $value): bool => $value !== null),
        ], static fn (mixed $value): bool => $value !== null);

        return array_merge($payload, $this->resolvePublicationPostTags($publication, $article, $isUpdate));
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolvePublicationPostTags(Publication $publication, Article $article, bool $isUpdate): array
    {
        $meta = is_array($publication->meta) ? $publication->meta : [];

        if (isset($meta['tag_ids']) && is_array($meta['tag_ids'])) {
            return ['tag_ids' => array_values($meta['tag_ids'])];
        }

        $tagNames = $article->tags
            ->pluck('name')
            ->filter(static fn (mixed $name): bool => is_string($name) && $name !== '')
            ->values()
            ->all();

        if ($tagNames !== []) {
            return ['tag_names' => $tagNames];
        }

        return $isUpdate ? ['tag_ids' => []] : [];
    }

    /**
     * @throws FlyCmsException
     */
    protected function resolveThumbnailFileId(Article $article): ?string
    {
        if (! $article->thumbnail_file_id || ! $thumbnailFile = $article->thumbnailFile) {
            return null;
        }

        $existingFile = $this->findFlyCmsFileByCode($thumbnailFile->id);

        if ($existingFile) {
            $fileId = $existingFile->get('id');

            return is_string($fileId) && $fileId !== '' ? $fileId : null;
        }

        $uploadedFile = $this->uploadArticleFile($thumbnailFile);
        $fileId = $uploadedFile?->get('id');

        if (! is_string($fileId) || $fileId === '') {
            throw new FlyCmsException('Failed to resolve thumbnail file for publishing.');
        }

        return $fileId;
    }

    /**
     * @throws FlyCmsException
     */
    protected function uploadArticleFile(File $file): ?FileResource
    {
        if (! is_string($file->path) || $file->path === '' || ! Storage::exists($file->path)) {
            return null;
        }

        $content = Storage::get($file->path);

        if (! is_string($content) || $content === '') {
            return null;
        }

        return $this->createFile(
            $content,
            (new CreateFileData)->setData([
                'ext' => $this->resolveImageFileExt($file),
                'code' => $file->id,
                'filename' => 'file-'.$file->id,
            ]),
        );
    }

    protected function resolvePostSlug(Article $article, Publication $publication): string
    {
        $title = $publication->title ?? $article->title ?? 'untitled-post';
        $slug = Str::slug($title);

        if ($slug !== '') {
            return $slug;
        }

        return 'post-'.Str::lower(substr($article->id, -8));
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

    protected function resolveImageFileExt(File $file): string
    {
        $ext = strtolower((string) ($file->extension ?? ''));

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
