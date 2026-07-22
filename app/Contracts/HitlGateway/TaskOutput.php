<?php

namespace App\Contracts\HitlGateway;

use App\Contracts\HitlGateway\Concerns\ResolvesFilesFromIds;
use App\Contracts\Serializable;
use App\Models\File;

class TaskOutput implements Serializable
{
    use \App\Concerns\Serializable;
    use ResolvesFilesFromIds;

    protected ?string $content = null;

    /** @var File[] */
    protected array $files = [];

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

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
    public function setFiles(iterable $files): static
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
            'content' => $this->getContent(),
            'files' => array_values(array_filter(array_map(
                static fn (File $file) => $file->getKey(),
                $this->getFiles()
            ))),
        ];
    }

    public static function fromArray(array $data): static
    {
        $output = new static;

        if (array_key_exists('content', $data)) {
            $output->setContent($data['content'] !== null ? (string) $data['content'] : null);
        }

        if (isset($data['files']) && is_array($data['files'])) {
            $output->setFiles(self::filesFromMixedList($data['files']));
        }

        return $output;
    }
}
