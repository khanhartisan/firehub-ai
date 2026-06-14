<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns;

use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\CreatePostData;
use App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData\UpdatePostData;
use App\Contracts\PlatformManager\PublishingResult;
use App\Enums\ArticleStatus;
use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\Publication;
use App\Utils\Str;

trait InteractsWithPseudoFlyCmsArticles
{
    public function publishArticle(Publication $publication): PublishingResult
    {
        $publication->loadMissing(['channel', 'publishable']);

        $article = $publication->publishable;

        if (! $article instanceof Article) {
            return new PublishingResult(PublicationStatus::ERROR);
        }

        if ($article->status !== ArticleStatus::READY) {
            return new PublishingResult(PublicationStatus::AWAITING);
        }

        $websiteId = $publication->channel?->reference;

        if (! is_string($websiteId) || $websiteId === '') {
            return new PublishingResult(PublicationStatus::FAILED);
        }

        $post = is_string($publication->reference) && $publication->reference !== ''
            ? $this->updatePost((new UpdatePostData)->setData([
                'id' => $publication->reference,
                ...$this->postPayloadFromPublication($publication, $article),
            ]))
            : $this->createPost((new CreatePostData)->setData([
                'website_id' => $websiteId,
                'slug' => $this->resolvePostSlug($article, $publication),
                ...$this->postPayloadFromPublication($publication, $article),
            ]));

        $postId = $post->get('id');

        if (! is_string($postId) || $postId === '') {
            return new PublishingResult(PublicationStatus::ERROR);
        }

        return new PublishingResult(PublicationStatus::PUBLISHED, $postId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function postPayloadFromPublication(Publication $publication, Article $article): array
    {
        $meta = is_array($publication->meta) ? $publication->meta : [];

        $title = $publication->title ?? $article->title;
        $description = $publication->description ?? $article->excerpt;

        return array_filter([
            'title' => $title,
            'description' => $description,
            'content' => $this->articleContentHtml($article),
            'seo_title' => $meta['seo_title'] ?? $title,
            'seo_description' => $meta['seo_description'] ?? $description,
            'visibility' => $meta['visibility'] ?? 'public',
            'restriction' => $meta['restriction'] ?? 0,
            'tag_ids' => $meta['tag_ids'] ?? [],
            'thumbnail_file_id' => $article->thumbnail_file_id,
        ], static fn (mixed $value): bool => $value !== null);
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
