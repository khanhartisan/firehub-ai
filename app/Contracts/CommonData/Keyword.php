<?php

namespace App\Contracts\CommonData;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;
use App\Enums\Country;
use App\Enums\Language;
use App\Utils\Str;

final class Keyword implements Serializable, \Stringable
{
    use SerializableTrait;

    protected string $keyword = '';

    protected ?Language $language = null;

    protected ?Country $country = null;

    public function __construct(?string $keyword = null)
    {
        if ($keyword !== null) {
            $this->setKeyword($keyword);
        }
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setKeyword(string $keyword): static
    {
        $keyword = Str::sanitizeKeyword($keyword);
        if ($keyword === '') {
            throw new \InvalidArgumentException('Keyword cannot be empty.');
        }

        $this->keyword = $keyword;

        return $this;
    }

    public function getLanguage(): ?Language
    {
        return $this->language;
    }

    public function setLanguage(?Language $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'keyword' => $this->getKeyword(),
            'language' => $this->getLanguage()?->value,
            'country' => $this->getCountry()?->value,
        ];
    }

    public static function fromArray(array $data): static
    {
        if (! isset($data['keyword']) || ! is_string($data['keyword'])) {
            throw new \InvalidArgumentException('Keyword requires a non-empty string "keyword".');
        }

        $instance = (new static($data['keyword']));

        if (array_key_exists('language', $data)) {
            $instance->setLanguage(
                $data['language'] instanceof Language
                    ? $data['language']
                    : (is_string($data['language']) ? Language::tryFrom($data['language']) : null)
            );
        }

        if (array_key_exists('country', $data)) {
            $instance->setCountry(
                $data['country'] instanceof Country
                    ? $data['country']
                    : (is_string($data['country']) ? Country::tryFrom($data['country']) : null)
            );
        }

        return $instance;
    }

    public function __toString()
    {
        return $this->getKeyword();
    }
}