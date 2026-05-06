<?php

namespace App\Contracts\Model\Client;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\CommonData\SemanticContextConcerns\HasAudienceContexts;
use App\Contracts\CommonData\SemanticContextConcerns\HasMeta;
use App\Utils\Str;

/**
 * Getters are handled by the magic method
 *
 * @method null|string getNameValue()
 * @method null|string getDescriptionValue()
 * @method null|string getToneOfVoiceValue()
 * @method null|string getIndustryValue()
 * @method null|array getNichesValue()
 * @method null|string getCoreMissionValue()
 * @method null|array getGuidelinesValue()
 * @method null|array getMetaValue()
 */
class Context extends SemanticContext
{
    use HasAudienceContexts;
    use HasMeta;

    public function setName(string $name, ?float $weight = null): static
    {
        return $this->set(
            'name',
            'Brand name of the website',
            $name,
            $weight
        );
    }

    public function setDescription(string $description, ?float $weight = null): static
    {
        return $this->set('description',
            'A high-level overview of the website\'s purpose',
            $description,
            $weight
        );
    }

    public function setToneOfVoice(string $toneOfVoice, ?float $weight = null): static
    {
        return $this->set(
            'tone_of_voice',
            'This describes the general tone and voice of the brand',
            $toneOfVoice,
            $weight
        );
    }

    public function setIndustry(string $industry, ?float $weight = null): static
    {
        return $this->set(
            'industry',
            'Broad industrial category (e.g., "Technology", "Fashion"). Use for high-level terminology and industry standards.',
            $industry,
            $weight
        );
    }

    public function setNiches(array $niches, ?float $weight = null): static
    {
        $niches = array_filter($niches, fn (mixed $niche) => is_string($niche));
        $niches = array_map(function (string $niche) {
            return Str::sanitizeKeyword($niche);
        }, $niches);
        $niches = array_unique($niches);

        return $this->set(
            'niches',
            'Highly specific market segments under the industry. Use these to narrow down expertise and target specific concerns.',
            $niches,
            $weight
        );
    }

    public function setGuidelines(array $guidelines, ?float $weight = null): static
    {
        return $this->set(
            'guidelines',
            'A collection of brand guidelines and constraints',
            array_filter($guidelines, fn ($guideline) => is_string($guideline)),
            $weight
        );
    }

    public function setCoreMission(string $coreMission, ?float $weight = null): static
    {
        return $this->set(
            'core_mission',
            'The fundamental purpose and long-term objective of the brand.',
            $coreMission,
            $weight
        );
    }
}

