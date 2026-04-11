<?php

namespace App\Contracts\IntentResolver;

use App\Contracts\Serializable;

final class Intentable implements Serializable
{
    use \App\Concerns\Serializable;

    protected string $content;

    public function getContent(): ?string
    {
        return $this->content ?? null;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
        ];
    }

    public static function fromArray(array $data): static
    {
        $sourceData = new self;

        if (isset($data['content'])) {
            $sourceData->setContent($data['content']);
        }

        return $sourceData;
    }
}
