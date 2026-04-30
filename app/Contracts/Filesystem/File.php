<?php

namespace App\Contracts\Filesystem;

use App\Contracts\Serializable;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\StreamInterface;

class File implements Serializable
{
    use \App\Concerns\Serializable;

    protected string $path;

    protected ?bool $exists;

    protected ?int $size;

    protected ?string $mimeType;

    protected ?StreamInterface $readStream;

    protected ?string $data;

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;
        return $this;
    }

    public function exists(): bool
    {
        return $this->exists ??= Storage::exists($this->getPath());
    }

    public function getSize(): int
    {
        return $this->size ??= Storage::size($this->getPath());
    }

    public function getMimeType(): ?string
    {
        return ($this->mimeType ??= Storage::mimeType($this->getPath())) ?: null;
    }

    public function getReadStream()
    {
        return $this->readStream ??= Storage::readStream($this->getPath());
    }

    public function getData(): ?string
    {
        return $this->data ??= Storage::get($this->getPath());
    }

    public function toArray(): array
    {
        return [
            'path' => $this->getPath(),
        ];
    }

    public static function fromArray(array $data): static
    {
        $file = new static();
        $file->setPath($data['path'] ?? throw new \InvalidArgumentException('File path not set.'));
        return $file;
    }
}