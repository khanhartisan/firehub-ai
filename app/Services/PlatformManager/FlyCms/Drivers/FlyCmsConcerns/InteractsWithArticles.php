<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\PlatformManager\FlyCms\Config;
use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\ContentFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\PartFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\SubjectFilter;
use App\Contracts\PlatformManager\FlyCms\Filters\ThumbnailFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\ContentMutationData\CreateContentData;
use App\Contracts\PlatformManager\FlyCms\MutationData\ContentMutationData\UpdateContentData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PartMutationData\CreatePartData;
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

        $subjectResource = $this->ensureSubject($article);
        $partResource = $this->ensurePart($subjectResource);
        $contentResource = $this->ensureContent($partResource, $publication);
        $thumbnailResource = $this->ensureThumbnail($subjectResource);
        $thumbnailFileResource = $this->ensureThumbnailFile($article, $thumbnailResource);
        $tagResources = $this->ensureTags($publication);

        $post = $this->ensurePost(
            $publication,
            $subjectResource,
            $contentResource,
            $thumbnailFileResource,
            $tagResources
        );

        if (!$postId = $post->get('id')) {
            return new PublishingResult(
                PublicationStatus::ERROR,
                null,
                'API server return null post ID'
            );
        }

        return new PublishingResult(
            PublicationStatus::PUBLISHED,
            $postId
        );
    }

    /**
     * @throws FlyCmsException
     */
    protected function ensureSubject(Article $article): SubjectResource
    {
        /** @var Config $config */
        $config = $this->getConfig();

        // Check if the subject exists
        $existingSubject = $this
            ->listResources(
                SubjectResource::class,
                1,
                1,
                null,
                new SubjectFilter([
                    'code' => $article->id
                ])
            );

        // Return if already exists
        if ($existingSubject) {
            /** @var SubjectResource */
            return $existingSubject[0];
        }

        // Create new if not
        /** @var SubjectResource */
        return $this->createResource(
            SubjectResource::class,
            new CreateSubjectData()
                ->setData([
                    'branch_id' => $config->getBranchId(),
                    'code' => $article->id,
                    'title' => $article->title,
                ]),
        );
    }

    /**
     * @throws FlyCmsException
     */
    protected function ensurePart(SubjectResource $subject): PartResource
    {
        $subjectId = $subject->get('id');

        if (! is_string($subjectId) || $subjectId === '') {
            throw new FlyCmsException('Subject id is required.');
        }

        $existingPart = $this->listResources(
            PartResource::class,
            1,
            1,
            null,
            (new PartFilter)->setFilterData([
                'subject_id' => $subjectId,
                'sequence' => 1,
            ]),
        );

        if ($existingPart) {
            /** @var PartResource */
            return $existingPart[0];
        }

        /** @var PartResource */
        return $this->createResource(
            PartResource::class,
            new CreatePartData()
                ->setData([
                    'subject_id' => $subjectId,
                    'sequence' => 1,
                    'title' => $subject->get('title'),
                    'description' => $subject->get('description'),
                ]),
        );
    }

    /**
     * @throws FlyCmsException
     */
    protected function ensureContent(PartResource $part, Publication $publication): ContentResource
    {
        $article = $publication->publishable;

        if (! $article instanceof Article) {
            throw new FlyCmsException('The publishable resource is not an instance of Article.');
        }

        $partId = $part->get('id');

        if (! is_string($partId) || $partId === '') {
            throw new FlyCmsException('Part id is required.');
        }

        $filterData = ['part_id' => $partId];

        if (is_string($publication->reference) && $publication->reference !== '') {
            $filterData['post_id'] = $publication->reference;
        }

        $existingContent = $this->listResources(
            ContentResource::class,
            1,
            1,
            null,
            (new ContentFilter)->setFilterData($filterData),
        );

        if ($existingContent) {
            $contentId = $existingContent[0]->get('id');

            if (! is_string($contentId) || $contentId === '') {
                throw new FlyCmsException('Content id is required.');
            }

            /** @var ContentResource */
            return $this->updateResource(
                ContentResource::class,
                $contentId,
                new UpdateContentData()
                    ->setData(array_filter([
                        'title' => $publication->title ?? $article->title,
                        'description' => $publication->description ?? $article->excerpt,
                        'content' => $this->articleContentHtml($article),
                        'post_id' => $publication->reference,
                    ], static fn (mixed $value): bool => $value !== null)),
            );
        }

        /** @var ContentResource */
        return $this->createResource(
            ContentResource::class,
            new CreateContentData()
                ->setData(array_filter([
                    'part_id' => $partId,
                    'lang' => $this->resolveContentLang($article),
                    'title' => $publication->title ?? $article->title,
                    'description' => $publication->description ?? $article->excerpt,
                    'content' => $this->articleContentHtml($article),
                    'post_id' => $publication->reference,
                ], static fn (mixed $value): bool => $value !== null)),
        );
    }

    /**
     * @throws FlyCmsException
     */
    protected function ensureThumbnail(SubjectResource $subjectResource): ThumbnailResource
    {
        $existingThumbnail = $this->listResources(
            ThumbnailResource::class,
            1,
            1,
            null,
            new ThumbnailFilter()->setFilterData([
                'branch_id' => $subjectResource->get('branch_id'),
                'subject_id' => $subjectId = $subjectResource->get('id'),
            ])
        );

        if ($existingThumbnail) {
            /** @var ThumbnailResource */
            return $existingThumbnail[0];
        }

        /** @var ThumbnailResource */
        return $this->createResource(
            ThumbnailResource::class,
            new CreateThumbnailData()
                ->setData([
                    'subject_id' => $subjectId
                ])
        );
    }

    protected function ensureThumbnailFile(Article $article,
                                           ThumbnailResource $thumbnailResource): ?FileResource
    {
        /** @var array $filesData */
        $filesData = $thumbnailResource->get('files') ?: [];

        if (!$article->thumbnail_file_id
            or !$thumbnailFile = $article->thumbnailFile
        ) {
            return $filesData[0] ?? null;
        }

        foreach ($filesData as $fileData) {
            $fileResource = FileResource::fromArray($fileData);
            if ($fileResource->get('code') === $thumbnailFile->id) {
                return $fileResource;
            }
        }

        return $this->uploadThumbnailFile($thumbnailFile, $thumbnailResource);
    }

    protected function uploadThumbnailFile(File $thumbnailFile,
                                           ThumbnailResource $thumbnailResource): FileResource
    {
        // TODO: Implement upload thumbnail file
    }

    /**
     * Ensure post
     *
     * @param Publication $publication
     * @param SubjectResource $subjectResource
     * @param ContentResource $contentResource
     * @param FileResource|null $thumbnailFile
     * @param array $tagResources
     * @return PostResource
     */
    protected function ensurePost(Publication $publication,
                                  SubjectResource $subjectResource,
                                  ContentResource $contentResource,
                                  ?FileResource $thumbnailFile = null,
                                  array $tagResources = []): PostResource
    {
        // TODO: Implement ensure post
    }

    /**
     * @param Publication $publication
     * @return TagResource[]
     */
    protected function ensureTags(Publication $publication): array
    {
        // TODO: Implement ensure tags
        return [];
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
