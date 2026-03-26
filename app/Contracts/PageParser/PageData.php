<?php

namespace App\Contracts\PageParser;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;
use App\Utils\UrlNormalizer;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Parsed page data returned by the PageParser (e.g. from scraped HTML).
 *
 * Contains extracted title, excerpt, markdown, dates, canonical info, and
 * linked page URLs used for discovery of new entities to scrape.
 */
final class PageData implements Serializable
{
    use SerializableTrait;

    protected string $title = '';

    protected string $excerpt = '';

    protected string $thumbnailUrl = '';

    /** Main content as markdown (used for storage and link/media counts). */
    protected string $markdownContent = '';

    protected ?CarbonInterface $publishedAt = null;

    protected ?CarbonInterface $updatedAt = null;

    protected ?CarbonInterface $fetchedAt = null;

    protected string $canonicalUrl = '';

    /** Ordinal in a series (e.g. part 1, 2, 3). */
    protected ?int $canonicalNumber = null;

    /** Absolute URLs to other pages (same host used for entity discovery). @var array<int, string> */
    protected array $linkedPageUrls = [];

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getExcerpt(): string
    {
        return $this->excerpt;
    }

    public function setExcerpt(string $excerpt): static
    {
        $this->excerpt = $excerpt;
        return $this;
    }

    public function getThumbnailUrl(): string
    {
        return $this->thumbnailUrl;
    }

    public function setThumbnailUrl(string $thumbnailUrl): static
    {
        $this->thumbnailUrl = $thumbnailUrl;
        return $this;
    }

    public function getMarkdownContent(): string
    {
        return $this->markdownContent;
    }

    public function setMarkdownContent(string $markdownContent): static
    {
        $this->markdownContent = $markdownContent;
        return $this;
    }

    public function getPublishedAt(): ?CarbonInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?CarbonInterface $publishedAt): static
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function getUpdatedAt(): ?CarbonInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?CarbonInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getFetchedAt(): ?CarbonInterface
    {
        return $this->fetchedAt;
    }

    public function setFetchedAt(?CarbonInterface $fetchedAt): static
    {
        $this->fetchedAt = $fetchedAt;
        return $this;
    }

    public function getCanonicalUrl(): string
    {
        return $this->canonicalUrl;
    }

    public function setCanonicalUrl(string $canonicalUrl): static
    {
        $this->canonicalUrl = $canonicalUrl;
        $this->setLinkedPageUrls($this->normalizeUrls($this->getLinkedPageUrls()));
        return $this;
    }

    public function getCanonicalNumber(): ?int
    {
        return $this->canonicalNumber;
    }

    public function setCanonicalNumber(?int $canonicalNumber): static
    {
        $this->canonicalNumber = $canonicalNumber;
        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getLinkedPageUrls(): array
    {
        return $this->linkedPageUrls;
    }

    /**
     * @param  array<int, string>  $linkedPageUrls
     */
    public function setLinkedPageUrls(array $linkedPageUrls): static
    {
        $this->linkedPageUrls = $this->normalizeUrls($linkedPageUrls);
        return $this;
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    protected function normalizeUrls(array $urls): array
    {
        $pageUrl = $this->getCanonicalUrl();
        if ($pageUrl === '') {
            return array_values($urls);
        }

        $out = [];
        foreach ($urls as $url) {
            $url = is_string($url) ? trim($url) : '';
            if ($url === '') {
                continue;
            }
            $out[] = UrlNormalizer::toFullUrl($pageUrl, $url);
        }

        return array_values(array_unique($out));
    }

    /**
     * Convert the page data to an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'thumbnail_url' => $this->thumbnailUrl,
            'markdown_content' => $this->markdownContent,
            'published_at' => $this->publishedAt?->toIso8601String(),
            'updated_at' => $this->updatedAt?->toIso8601String(),
            'fetched_at' => $this->fetchedAt?->toIso8601String(),
            'canonical_url' => $this->canonicalUrl,
            'canonical_number' => $this->canonicalNumber,
            'linked_page_urls' => $this->linkedPageUrls,
        ];
    }

    /**
     * Create an instance from an array representation.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $pageData = new static;

        if (isset($data['title'])) {
            $pageData->setTitle($data['title']);
        }

        if (isset($data['excerpt'])) {
            $pageData->setExcerpt($data['excerpt']);
        }

        if (isset($data['thumbnail_url'])) {
            $pageData->setThumbnailUrl($data['thumbnail_url']);
        }

        if (isset($data['markdown_content'])) {
            $pageData->setMarkdownContent($data['markdown_content']);
        }

        if (isset($data['published_at'])) {
            $pageData->setPublishedAt($data['published_at'] ? Carbon::parse($data['published_at']) : null);
        }

        if (isset($data['updated_at'])) {
            $pageData->setUpdatedAt($data['updated_at'] ? Carbon::parse($data['updated_at']) : null);
        }

        if (isset($data['fetched_at'])) {
            $pageData->setFetchedAt($data['fetched_at'] ? Carbon::parse($data['fetched_at']) : null);
        }

        if (isset($data['canonical_url'])) {
            $pageData->setCanonicalUrl($data['canonical_url']);
        }

        if (isset($data['canonical_number'])) {
            $pageData->setCanonicalNumber($data['canonical_number'] !== null ? (int) $data['canonical_number'] : null);
        }

        if (isset($data['linked_page_urls']) && is_array($data['linked_page_urls'])) {
            $pageData->setLinkedPageUrls($data['linked_page_urls']);
        }

        return $pageData;
    }
}