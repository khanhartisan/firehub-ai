<?php

namespace App\Contracts\Synthesizer\Illustration;

use App\Concerns\AlwaysIdentifiable;
use App\Contracts\Filesystem\File;
use App\Contracts\Identifiable;
use App\Contracts\Serializable;
use App\Enums\AspectRatio;

class IllustrationResult implements Identifiable, Serializable
{
    use AlwaysIdentifiable;
    use \App\Concerns\Serializable;

    protected ?IllustrationContext $illustrationContext = null;

    protected ?AspectRatio $aspectRatio = null;

    protected ?string $seed = null;

    /**
     * @var File[]
     */
    protected array $files = [];

    public function getIllustrationContext(): ?IllustrationContext
    {
        return $this->illustrationContext;
    }

    public function setIllustrationContext(?IllustrationContext $illustrationContext): static
    {
        $this->illustrationContext = $illustrationContext;
        return $this;
    }

    public function getAspectRatio(): ?AspectRatio
    {
        return $this->aspectRatio;
    }

    public function setAspectRatio(?AspectRatio $aspectRatio): static
    {
        $this->aspectRatio = $aspectRatio;
        return $this;
    }

    public function getSeed(): ?string
    {
        return $this->seed;
    }

    public function setSeed(?string $seed): static
    {
        $this->seed = $seed;
        return $this;
    }

    /**
     * @return File[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @param File[] $images
     * @return static
     */
    public function setFiles(array $images): static
    {
        $this->files = [];

        foreach ($images as $image) {
            $this->addFile($image);
        }

        return $this;
    }

    public function addFile(File $file): static
    {
        if (array_filter(
            $this->getFiles(),
            function (File $existingFile) use ($file) {
                return $existingFile->getPath() === $file->getPath();
            }
        )) {
            return $this;
        }

        $this->files[] = $file;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'identifier' => $this->getIdentifier(),
            'illustration_context' => $this->getIllustrationContext()?->toArray(),
            'aspect_ratio' => $this->getAspectRatio()?->value,
            'seed' => $this->getSeed(),
            'files' => array_map(
                fn (File $file) => $file->toArray(),
                $this->getFiles()
            ),
        ];
    }

    public static function fromArray(array $data): static
    {
        $result = new static();

        if (isset($data['identifier']) && is_string($data['identifier']) && $data['identifier'] !== '') {
            $result->setIdentifier($data['identifier']);
        }

        if (isset($data['illustration_context']) && is_array($data['illustration_context'])) {
            $result->setIllustrationContext(IllustrationContext::fromArray($data['illustration_context']));
        }

        if (isset($data['aspect_ratio']) && is_string($data['aspect_ratio'])) {
            $result->setAspectRatio(AspectRatio::tryFrom($data['aspect_ratio']));
        }

        if (isset($data['seed']) && is_string($data['seed'])) {
            $result->setSeed($data['seed']);
        }

        if (isset($data['files']) && is_array($data['files'])) {
            foreach ($data['files'] as $file) {
                $result->addFile($file instanceof File
                    ? $file
                    : File::fromArray($file)
                );
            }
        }

        return $result;
    }
}