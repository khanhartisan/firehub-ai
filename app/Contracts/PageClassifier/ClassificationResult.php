<?php

namespace App\Contracts\PageClassifier;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Describable;
use App\Contracts\Serializable;
use App\Enums\ContentType;
use App\Enums\PageType;
use App\Enums\Temporal;

/**
 * Result of classifying a scraped HTML page (e.g. via PageClassifier).
 *
 * Holds page type, content type, temporal classification, and tags used to
 * categorize entities and drive downstream logic (policy, filtering).
 */
final class ClassificationResult implements Serializable
{
    use SerializableTrait;

    /** Classification: article, product, etc. */
    protected ContentType $contentType;

    /** Page role: listing, detail, home, etc. */
    protected PageType $pageType;

    /** Time relevance: evergreen, time-sensitive, etc. */
    protected Temporal $temporal;

    /** Normalized tag names (lowercase, trimmed) for the page. @var array<int, string> */
    protected array $tags = [];

    /**
     * @return array<int, string>
     */
    public function getTags(): array
    {
        return $this->tags ?? [];
    }

    /**
     * @param array<int, string> $tags
     */
    public function setTags(array $tags): static
    {
        $this->clearTags();
        foreach ($tags as $tag) {
            $this->addTag($tag);
        }

        return $this;
    }

    public function clearTags(): static
    {
        $this->tags = [];
        return $this;
    }

    public function addTag(string $tag): static
    {
        $tag = $this->normalizeTag($tag);

        foreach ($this->tags ?? [] as $i => $existingTag) {
            if ($this->normalizeTag($existingTag) === $tag) {
                // Normalize existing tag casing/whitespace in-place.
                $this->tags[$i] = $tag;
                return $this;
            }
        }

        $this->tags[] = $tag;

        return $this;
    }

    public function removeTag(string $tag): static
    {
        $normalizedTag = $this->normalizeTag($tag);

        $remaining = array_values(array_filter(
            $this->tags ?? [],
            fn (string $t): bool => $this->normalizeTag($t) !== $normalizedTag
        ));

        return $this->setTags($remaining);
    }

    private function normalizeTag(string $tag): string
    {
        $tag = trim($tag);

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($tag);
        }

        return strtolower($tag);
    }

    public function getContentType(): ?ContentType
    {
        return $this->contentType ?? null;
    }

    public function setContentType(ContentType $contentType): static
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function getPageType(): ?PageType
    {
        return $this->pageType ?? null;
    }

    public function setPageType(PageType $pageType): static
    {
        $this->pageType = $pageType;
        return $this;
    }

    public function getTemporal(): ?Temporal
    {
        return $this->temporal ?? null;
    }

    public function setTemporal(Temporal $temporal): static
    {
        $this->temporal = $temporal;
        return $this;
    }

    /**
     * Convert the classification result to an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content_type' => $this->contentType?->value ?? null,
            'page_type' => $this->pageType?->value ?? null,
            'temporal' => $this->temporal?->value ?? null,
            'tags' => $this->tags ?? [],
        ];
    }

    /**
     * Create an instance from an array representation.
     *
     * @param  array<string, mixed>  $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $result = new static();

        if (isset($data['content_type'])) {
            $contentType = ContentType::tryFrom($data['content_type']);
            if ($contentType !== null) {
                $result->setContentType($contentType);
            }
        }

        if (isset($data['page_type'])) {
            $pageType = PageType::tryFrom($data['page_type']);
            if ($pageType !== null) {
                $result->setPageType($pageType);
            }
        }

        if (isset($data['temporal'])) {
            $temporal = Temporal::tryFrom($data['temporal']);
            if ($temporal !== null) {
                $result->setTemporal($temporal);
            }
        }

        if (isset($data['tags']) && is_array($data['tags'])) {
            $result->setTags($data['tags']);
        }

        return $result;
    }
}