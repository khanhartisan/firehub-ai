<?php

namespace App\Contracts\Synthesizer\Author\AuthorContexts;

use App\Contracts\CommonData\SemanticContext;

class DemographicContext extends SemanticContext
{
    public function setAgeCohort(string $ageCohort): static
    {
        return $this->set(
            'age_cohort',
            'The chronological age or generational cohort (e.g., "Gen X", "Mid-30s"). We can use this to determine the era of the author\'s nostalgia',
            $ageCohort
        );
    }

    public function setLocationHistory(string $locationHistory): static
    {
        return $this->set(
            'location_history',
            'Where the author grew up, where did the author moved to, where they live now...',
            $locationHistory
        );
    }

    public function setGenderIdentity(string $genderIdentity): static
    {
        return $this->set(
            'gender_identity',
            'The author\'s gender identity and presentation. Subtly influences their perspective on societal norms, workplace dynamics, and interpersonal relationships',
            $genderIdentity
        );
    }

    public function setSocioeconomicStatus(string $socioeconomicStatus): static
    {
        return $this->set(
            'socioeconomic_status',
            'The economic background of the author (e.g., "Working-class roots", "Generational wealth"). This profoundly affects the author\'s tone regarding money, risk, privilege, and hard work.',
            $socioeconomicStatus
        );
    }

    public function setLifestyleConstraints(array $lifestyleConstraints): static
    {
        return $this->set(
            'lifestyle_constraints',
            'The author\'s daily reality outside of their profession. (e.g., ["have 3 kids", "have a mortgage", "is a digital nomad"])',
            $lifestyleConstraints
        );
    }
}