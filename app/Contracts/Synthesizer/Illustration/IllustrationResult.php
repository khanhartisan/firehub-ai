<?php

namespace App\Contracts\Synthesizer\Illustration;

use App\Contracts\Filesystem\File;
use App\Contracts\Serializable;
use App\Enums\AspectRatio;

class IllustrationResult implements Serializable
{
    use \App\Concerns\Serializable;

    protected ?AspectRatio $aspectRatio = null;

    protected ?string $seed = null;

    /**
     * @var File[]
     */
    protected array $files = [];

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