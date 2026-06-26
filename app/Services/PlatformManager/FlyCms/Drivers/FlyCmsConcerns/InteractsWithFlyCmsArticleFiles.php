<?php

namespace App\Services\PlatformManager\FlyCms\Drivers\FlyCmsConcerns;

use App\Contracts\DOM\Article as DOMArticle;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Contracts\Filesystem\File as FilesystemFile;
use App\Contracts\Model\Article\IllustrationData;
use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\Filters\FileFilter;
use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\CreateFileData;
use App\Contracts\PlatformManager\FlyCms\Resources\FileResource;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use App\Contracts\Synthesizer\Writer\IllustrationAnchor;
use App\Models\Article;
use Illuminate\Support\Facades\Storage;

trait InteractsWithFlyCmsArticleFiles
{
    /**
     * @return list<int>
     */
    protected function flyCmsResponsiveImageWidths(): array
    {
        return [400, 600, 850];
    }

    /**
     * Composes the text-only article DOM with illustration files at anchor points,
     * uploads each file to FlyCMS, and returns the resulting HTML.
     *
     * @throws FlyCmsException
     */
    protected function articleContentHtml(Article $article): ?string
    {
        $baseArticle = $article->article;
        if (! $baseArticle instanceof DOMArticle) {
            return null;
        }

        $composed = DOMArticle::fromArray($baseArticle->toArray());

        $illustration = $article->illustration;
        if ($illustration instanceof IllustrationData) {
            $this->composeIllustrationIntoFlyCmsArticle($composed, $illustration);
        }

        $html = $composed->toHtml();

        return $html !== '' ? $html : null;
    }

    /**
     * @throws FlyCmsException
     */
    protected function composeIllustrationIntoFlyCmsArticle(DOMArticle $article, IllustrationData $illustration): void
    {
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

                $imgElement = $this->buildFlyCmsIllustrationImageElement($result);
                if (! $imgElement instanceof Element) {
                    continue;
                }

                $targetParent = $this->findDirectParentForElementId($article, $anchor->getElementIdentifier());
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
    }

    /**
     * @throws FlyCmsException
     */
    protected function buildFlyCmsIllustrationImageElement(IllustrationResult $result): ?Element
    {
        $files = $result->getFiles();
        $first = $files[0] ?? null;
        if (! $first instanceof FilesystemFile) {
            return null;
        }

        $path = trim($first->getPath());
        if ($path === '') {
            return null;
        }

        $flyCmsFile = $this->resolveOrUploadFlyCmsFile(
            $this->flyCmsCodeForStoragePath($path),
            $path,
            $this->resolveImageExtFromPath($path),
        );

        $fileKey = $flyCmsFile->get('key');
        if (! is_string($fileKey) || $fileKey === '') {
            throw new FlyCmsException('Failed to resolve FlyCMS file key for article image.');
        }

        $widths = $this->flyCmsResponsiveImageWidths();
        $defaultWidth = $widths[array_key_last($widths)] ?? 850;

        return (new Element)
            ->setType(ElementType::IMG)
            ->setProp('src', $this->flyCmsImageLiquidUrl($fileKey, $defaultWidth))
            ->setProp('srcset', $this->flyCmsImageSrcset($fileKey, $widths))
            ->setProp('sizes', '(max-width: 640px) 100vw, (max-width: 1024px) 80vw, 850px')
            ->setProp('alt', $result->getIllustrationContext()?->getSubjectValue() ?: '');
    }

    /**
     * @throws FlyCmsException
     */
    protected function resolveOrUploadFlyCmsFile(string $code, string $storagePath, ?string $extension = null): FileResource
    {
        $existingFile = $this->findFlyCmsFileByCode($code);
        if ($existingFile instanceof FileResource) {
            return $existingFile;
        }

        $content = $this->readStorageFileContent($storagePath);
        if (! is_string($content) || $content === '') {
            throw new FlyCmsException('Failed to read article file from storage.');
        }

        return $this->createFile(
            $content,
            (new CreateFileData)->setData([
                'ext' => $extension ?? $this->resolveImageExtFromPath($storagePath),
                'code' => $code,
                'filename' => 'file-'.$code,
            ]),
        );
    }

    protected function flyCmsCodeForStoragePath(string $storagePath): string
    {
        return 'storage-'.substr(hash('sha256', $storagePath), 0, 40);
    }

    protected function readStorageFileContent(string $path): ?string
    {
        if (Storage::exists($path)) {
            $content = Storage::get($path);

            return is_string($content) ? $content : null;
        }

        if (Storage::disk('public')->exists($path)) {
            $content = Storage::disk('public')->get($path);

            return is_string($content) ? $content : null;
        }

        return null;
    }

    protected function flyCmsImageLiquidUrl(string $fileKey, int $width): string
    {
        $escapedKey = str_replace("'", "\\'", $fileKey);

        return "{{ '{$escapedKey}' | image_url: {$width} }}";
    }

    /**
     * @param  list<int>  $widths
     */
    protected function flyCmsImageSrcset(string $fileKey, array $widths): string
    {
        $parts = [];
        foreach ($widths as $width) {
            $parts[] = $this->flyCmsImageLiquidUrl($fileKey, $width).' '.$width.'w';
        }

        return implode(', ', $parts);
    }

    protected function resolveImageExtFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return $ext;
        }

        return 'jpg';
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
