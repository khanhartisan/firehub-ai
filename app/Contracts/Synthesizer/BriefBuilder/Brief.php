<?php

namespace App\Contracts\Synthesizer\BriefBuilder;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;
use App\Enums\Temporal;
use App\Models\Page;
use App\Utils\Str;
use Illuminate\Database\Eloquent\Collection;

/**
 * Brief payload for the synthesizer: temporal scope, title, description,
 * instructions, and reference page IDs used to ground generation.
 */
final class Brief implements Serializable
{
    use SerializableTrait;

    protected ?Temporal $temporal = null;

    protected ?string $title = null;

    protected ?string $description = null;

    /**
     * @var string[]
     */
    protected array $keywords = [];

    /**
     * @var string[]
     */
    protected array $instructions = [];

    /**
     * @var string[]
     */
    protected array $referencePageIds = [];

    public function getTemporal(): ?Temporal
    {
        return $this->temporal;
    }

    public function setTemporal(?Temporal $temporal): static
    {
        $this->temporal = $temporal;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /**
     * @param  string[]  $keywords
     */
    public function setKeywords(array $keywords): static
    {
        $this->keywords = [];
        foreach ($keywords as $keyword) {
            $this->addKeyword((string) $keyword);
        }

        return $this;
    }

    public function addKeyword(string $keyword): static
    {
        $normalizedKeyword = Str::sanitizeKeyword($keyword);

        if ($normalizedKeyword === '' || in_array($normalizedKeyword, $this->keywords, true)) {
            return $this;
        }

        $this->keywords[] = $normalizedKeyword;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getInstructions(): array
    {
        return $this->instructions;
    }

    /**
     * @param  string[]  $instructions
     */
    public function setInstructions(array $instructions): static
    {
        $this->instructions = array_values(array_map(static fn ($line) => (string) $line, $instructions));

        return $this;
    }

    /**
     * @return string[]
     */
    public function getReferencePageIds(): array
    {
        return $this->referencePageIds;
    }

    /**
     * @param  string[]  $referencePageIds
     */
    public function setReferencePageIds(array $referencePageIds): static
    {
        $this->referencePageIds = array_values(array_map(static fn ($id) => (string) $id, $referencePageIds));

        return $this;
    }

    /**
     * @return Collection<Page>
     */
    public function getReferencePages(): Collection
    {
        if (! $this->getReferencePageIds()) {
            return new Collection;
        }

        return Page::query()->whereIn('id', $this->getReferencePageIds())->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'temporal' => $this->getTemporal()?->value ?? null,
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'keywords' => $this->getKeywords(),
            'instructions' => $this->getInstructions(),
            'reference_page_ids' => $this->getReferencePageIds(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $brief = new static;

        if (array_key_exists('temporal', $data)) {
            $raw = $data['temporal'];
            if ($raw === null || $raw === '') {
                $brief->setTemporal(null);
            } else {
                $brief->setTemporal($raw instanceof Temporal ? $raw : Temporal::tryFrom((string) $raw));
            }
        }

        if (isset($data['title'])) {
            $brief->setTitle($data['title'] !== null ? (string) $data['title'] : null);
        }

        if (isset($data['description'])) {
            $brief->setDescription($data['description'] !== null ? (string) $data['description'] : null);
        }

        if (isset($data['keywords']) && is_array($data['keywords'])) {
            $brief->setKeywords($data['keywords']);
        }

        if (isset($data['instructions']) && is_array($data['instructions'])) {
            $brief->setInstructions($data['instructions']);
        }

        if (isset($data['reference_page_ids']) && is_array($data['reference_page_ids'])) {
            $brief->setReferencePageIds($data['reference_page_ids']);
        }

        return $brief;
    }
}
