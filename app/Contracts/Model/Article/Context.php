<?php

namespace App\Contracts\Model\Article;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\CommonData\SemanticContextConcerns\HasAudienceContexts;
use App\Contracts\CommonData\SemanticContextConcerns\HasMeta;

class Context extends SemanticContext
{
    use HasAudienceContexts;
    use HasMeta;

    public function setIdeaGuidelines(array $guidelines, ?float $weight = null): static
    {
        return $this->set('idea_guidelines',
            'A collection of guidelines for the idea of this article',
            array_filter($guidelines, fn ($guideline) => is_string($guideline)),
            $weight);
    }

    public function setToneOfVoice(string $toneOfVoice, ?float $weight = null): static
    {
        return $this->set(
            'tone_of_voice',
            'This describes the tone and voice of this article.',
            $toneOfVoice,
            $weight
        );
    }

    public function setGuidelines(array $guidelines, ?float $weight = null): static
    {
        return $this->set(
            'guidelines',
            'A collection of guidelines for this article.',
            array_filter($guidelines, fn ($guideline) => is_string($guideline)),
            $weight
        );
    }
}