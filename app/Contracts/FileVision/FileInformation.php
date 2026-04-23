<?php

namespace App\Contracts\FileVision;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Describable;
use App\Contracts\Serializable;

/**
 * Result of FileVision::describe(): file metadata and optional description.
 *
 * Holds path, extension, MIME type, confidence score, and Describable description
 * for use after analyzing a stored file.
 */
final class FileInformation implements Describable, Serializable
{
    use SerializableTrait;
    use \App\Concerns\Describable;

    protected string $filePath;

    protected string $extension;

    protected string $mimeType;

    /** Confidence score for the detection (0–1). */
    protected float $confidence;

    public function getFilePath(): ?string
    {
        return $this->filePath ?? null;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension ?? null;
    }

    public function setExtension(string $extension): static
    {
        $this->extension = $extension;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType ?? null;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence ?? null;
    }

    public function setConfidence(float $confidence): static
    {
        $this->confidence = round($confidence, 2);
        return $this;
    }

    /**
     * Convert the file information to an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'file_path' => $this->filePath ?? null,
            'extension' => $this->extension ?? null,
            'mime_type' => $this->mimeType ?? null,
            'confidence' => $this->confidence ?? null,
            'description' => $this->getDescription(),
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
        $fileInfo = new static();

        if (isset($data['file_path'])) {
            $fileInfo->setFilePath($data['file_path']);
        }

        if (isset($data['extension'])) {
            $fileInfo->setExtension($data['extension']);
        }

        if (isset($data['mime_type'])) {
            $fileInfo->setMimeType($data['mime_type']);
        }

        if (isset($data['confidence'])) {
            $fileInfo->setConfidence($data['confidence']);
        }

        if (isset($data['description'])) {
            $fileInfo->setDescription($data['description']);
        }

        return $fileInfo;
    }
}