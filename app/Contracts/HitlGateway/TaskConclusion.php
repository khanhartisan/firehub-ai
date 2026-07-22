<?php

namespace App\Contracts\HitlGateway;

use App\Contracts\HitlGateway\Concerns\ResolvesFilesFromIds;
use App\Contracts\Serializable;
use App\Models\File;

class TaskConclusion implements Serializable
{
    use \App\Concerns\Serializable;
    use ResolvesFilesFromIds;

    /**
     * Whether the human fully settled the task concern (answered completely,
     * insisted it was answered, or acknowledged they cannot answer).
     * False when information is missing or only partially answered.
     */
    protected bool $resolved = false;

    protected ?string $conclusion = null;

    /** @var File[] */
    protected array $files = [];

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function setResolved(bool $resolved): static
    {
        $this->resolved = $resolved;

        return $this;
    }

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
            'resolved' => $this->isResolved(),
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

        if (array_key_exists('resolved', $data)) {
            $conclusion->setResolved((bool) $data['resolved']);
        }

        if (array_key_exists('conclusion', $data)) {
            $conclusion->setConclusion($data['conclusion'] !== null ? (string) $data['conclusion'] : null);
        }

        if (isset($data['files']) && is_array($data['files'])) {
            $conclusion->setFiles(self::filesFromMixedList($data['files']));
        }

        return $conclusion;
    }
}
