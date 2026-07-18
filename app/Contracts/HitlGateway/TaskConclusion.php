<?php

namespace App\Contracts\HitlGateway;

use App\Contracts\HitlGateway\Concerns\ResolvesFilesFromIds;
use App\Contracts\Serializable;
use App\Models\File;

class TaskConclusion implements Serializable
{
    use \App\Concerns\Serializable;
    use ResolvesFilesFromIds;

    protected ?string $conclusion = null;

    /** @var File[] */
    protected array $files = [];

    public function getConclusion(): ?string
    {
        return $this->conclusion;
    }

    public function setConclusion(?string $conclusion): static
    {
        $this->conclusion = $conclusion;

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
     * @param  File[]  $files
     */
    public function setFiles(array $files): static
    {
        $this->files = [];

        foreach ($files as $file) {
            $this->addFile($file);
        }

        return $this;
    }

    public function addFile(File $file): static
    {
        $this->files[] = $file;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'conclusion' => $this->getConclusion(),
            'files' => array_values(array_filter(array_map(
                static fn (File $file) => $file->getKey(),
                $this->getFiles()
            ))),
        ];
    }

    public static function fromArray(array $data): static
    {
        $conclusion = new static;

        if (array_key_exists('conclusion', $data)) {
            $conclusion->setConclusion($data['conclusion'] !== null ? (string) $data['conclusion'] : null);
        }

        if (isset($data['files']) && is_array($data['files'])) {
            $conclusion->setFiles(self::filesFromMixedList($data['files']));
        }

        return $conclusion;
    }
}
