<?php

namespace App\Contracts\PlatformManager;

use App\Contracts\Serializable;
use App\Enums\PublicationStatus;

final readonly class PublishingResult implements Serializable
{
    use \App\Concerns\Serializable;

    public function __construct(protected PublicationStatus $status, protected ?string $reference = null)
    {

    }

    public function getStatus(): PublicationStatus
    {
        return $this->status;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'reference' => $this->reference,
        ];
    }

    public static function fromArray(array $data): static
    {
        $status = $data['status'] ?? throw new \Exception('status is missing');
        if (!$status = PublicationStatus::tryFrom($status)) {
            throw new \Exception('status is invalid');
        }

        if (isset($data['reference']) and !is_string($data['reference'])) {
            throw new \Exception('reference must be a string');
        }

        return new static(
            $status,
            $data['reference'] ?? null
        );
    }
}