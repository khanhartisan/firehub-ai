<?php

namespace App\Casts;

use App\Contracts\DOM\Article as DOMArticle;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Contracts\Filesystem\File;
use App\Contracts\Model\Article\IllustrationData;
use App\Contracts\Synthesizer\Writer\IllustrationAnchor;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ArticleIllustratedArticleCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): DOMArticle
    {
        $base = $this->resolveBaseArticle($attributes['article'] ?? null);
        $illustrated = DOMArticle::fromArray($base->toArray());
        $illustration = $this->resolveIllustrationData($attributes['illustration'] ?? null);

        $resultMap = [];
        foreach ($illustration->getIllustrationResults() as $result) {
            if ($result instanceof IllustrationResult) {
                $resultMap[$result->getIdentifier()] = $result;
            }
        }

        $groupedAnchors = [];
        foreach ($illustration->getIllustrationAnchors() as $anchor) {
            if (! $anchor instanceof IllustrationAnchor) {
                continue;
            }
            $groupKey = $anchor->getElementIdentifier()."\0".($anchor->isAfter() ? '1' : '0');
            $groupedAnchors[$groupKey][] = $anchor;
        }

        foreach ($groupedAnchors as $anchors) {
            foreach (array_reverse($anchors) as $anchor) {
                $result = $resultMap[$anchor->getIllustrationIdentifier()] ?? null;
                if (! $result instanceof IllustrationResult) {
                    continue;
                }

                $imgElement = $this->buildIllustrationImageElement($result);
                if (! $imgElement instanceof Element) {
                    continue;
                }

                $targetParent = $this->findDirectParentForElementId($illustrated, $anchor->getElementIdentifier());
                if (! $targetParent instanceof Element) {
                    continue;
                }

                try {
                    if ($anchor->isAfter()) {
                        $targetParent->insertAfter($anchor->getElementIdentifier(), $imgElement);
                    } else {
                        $targetParent->insertBefore($anchor->getElementIdentifier(), $imgElement);
                    }
                } catch (\Exception) {
                    // anchor element no longer present in DOM — skip
                }
            }
        }

        return $illustrated;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return null;
    }

    protected function resolveBaseArticle(mixed $value): DOMArticle
    {
        if ($value instanceof DOMArticle) {
            return $value;
        }

        if (is_array($value)) {
            return DOMArticle::fromArray($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return DOMArticle::fromArray($decoded);
            }
        }

        return DOMArticle::fromArray([]);
    }

    protected function resolveIllustrationData(mixed $value): IllustrationData
    {
        if ($value instanceof IllustrationData) {
            return $value;
        }

        if (is_array($value)) {
            return IllustrationData::fromArray($value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return IllustrationData::fromArray($decoded);
            }
        }

        return IllustrationData::fromArray([]);
    }

    protected function buildIllustrationImageElement(IllustrationResult $result): ?Element
    {
        $files = $result->getFiles();
        $first = $files[0] ?? null;
        if (! $first instanceof File) {
            return null;
        }

        $path = trim($first->getPath());
        if ($path === '') {
            return null;
        }

        $src = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : Storage::url($path);

        return (new Element)
            ->setType(ElementType::IMG)
            ->setProp('src', $src)
            ->setProp('alt', $result->getIllustrationContext()?->getSubjectValue() ?: '');
    }

    protected function findDirectParentForElementId(Element $root, string $targetIdentifier): ?Element
    {
        foreach ($root->getChildren() as $child) {
            if (! $child instanceof Element) {
                continue;
            }

            if ($child->getIdentifier() === $targetIdentifier) {
                return $root;
            }

            $found = $this->findDirectParentForElementId($child, $targetIdentifier);
            if ($found instanceof Element) {
                return $found;
            }
        }

        return null;
    }
}
