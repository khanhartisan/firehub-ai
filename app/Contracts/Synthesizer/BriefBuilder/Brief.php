<?php

namespace App\Contracts\Synthesizer\BriefBuilder;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;
use App\Enums\ContentGoal;
use App\Enums\ContentTone;
use App\Enums\ContentVoice;
use App\Enums\Temporal;
use App\Models\Page;
use Illuminate\Database\Eloquent\Collection;

/**
 * Brief payload for the synthesizer: temporal scope, title, description,
 * instructions, and reference page IDs used to ground generation.
 */
final class Brief implements Serializable
{
    use SerializableTrait;

    protected ?Temporal $temporal = null;

    /** @var Audience[] */
    protected array $audiences = [];

    protected ?string $title = null;

    protected ?string $description = null;

    protected ?ContentGoal $goal = null;

    protected ?ContentVoice $voice = null;

    protected ?ContentTone $tone = null;

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

    /**
     * @return Audience[]
     */
    public function getAudiences(): array
    {
        return $this->audiences;
    }

    /**
     * @param  Audience[]  $audiences
     */
    public function setAudiences(array $audiences): static
    {
        $this->audiences = [];
        foreach ($audiences as $index => $audience) {
            if (! $audience instanceof Audience) {
                throw new \InvalidArgumentException(
                    sprintf('audiences[%s] must be an instance of %s, %s given.', $index, Audience::class, get_debug_type($audience))
                );
            }

            $this->audiences[] = $audience;
        }

        return $this;
    }

    public function addAudience(Audience $audience): static
    {
        $this->audiences[] = $audience;

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

    public function getGoal(): ?ContentGoal
    {
        return $this->goal;
    }

    public function setGoal(?ContentGoal $goal): static
    {
        $this->goal = $goal;

        return $this;
    }

    public function getVoice(): ?ContentVoice
    {
        return $this->voice;
    }

    public function setVoice(?ContentVoice $voice): static
    {
        $this->voice = $voice;

        return $this;
    }

    public function getTone(): ?ContentTone
    {
        return $this->tone;
    }

    public function setTone(?ContentTone $tone): static
    {
        $this->tone = $tone;

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
            'audiences' => array_map(static fn (Audience $audience): array => $audience->toArray(), $this->getAudiences()),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'goal' => $this->getGoal()?->value,
            'voice' => $this->getVoice()?->value,
            'tone' => $this->getTone()?->value,
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

        if (isset($data['audiences']) && is_array($data['audiences'])) {
            $audiences = [];
            foreach ($data['audiences'] as $row) {
                if ($row instanceof Audience) {
                    $audiences[] = $row;

                    continue;
                }

                if (is_array($row)) {
                    $audiences[] = Audience::fromArray($row);
                }
            }
            $brief->setAudiences($audiences);
        }

        if (isset($data['title'])) {
            $brief->setTitle($data['title'] !== null ? (string) $data['title'] : null);
        }

        if (isset($data['description'])) {
            $brief->setDescription($data['description'] !== null ? (string) $data['description'] : null);
        }

        if (array_key_exists('goal', $data)) {
            $raw = $data['goal'];
            if ($raw === null || $raw === '') {
                $brief->setGoal(null);
            } else {
                $brief->setGoal($raw instanceof ContentGoal ? $raw : ContentGoal::tryFrom((string) $raw));
            }
        }

        if (array_key_exists('voice', $data)) {
            $raw = $data['voice'];
            if ($raw === null || $raw === '') {
                $brief->setVoice(null);
            } else {
                $brief->setVoice($raw instanceof ContentVoice ? $raw : ContentVoice::tryFrom((string) $raw));
            }
        }

        if (array_key_exists('tone', $data)) {
            $raw = $data['tone'];
            if ($raw === null || $raw === '') {
                $brief->setTone(null);
            } else {
                $brief->setTone($raw instanceof ContentTone ? $raw : ContentTone::tryFrom((string) $raw));
            }
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
