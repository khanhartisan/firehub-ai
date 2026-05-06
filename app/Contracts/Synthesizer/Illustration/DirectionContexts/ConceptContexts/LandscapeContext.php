<?php

namespace App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptContexts;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptConcerns\HasAbstractionContexts;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptConcerns\HasCharacterContexts;
use App\Contracts\Synthesizer\Illustration\DirectionContexts\ConceptConcerns\HasObjectContexts;

/**
 * @method null|array getSetting()
 * @method null|string getSettingValue()
 * @method null|string getSettingDescription()
 * @method null|array getLocation()
 * @method null|string getLocationValue()
 * @method null|string getLocationDescription()
 * @method null|array getTerrain()
 * @method null|string getTerrainValue()
 * @method null|string getTerrainDescription()
 * @method null|array getVegetation()
 * @method null|string getVegetationValue()
 * @method null|string getVegetationDescription()
 * @method null|array getStructures()
 * @method null|array getStructuresValue()
 * @method null|string getStructuresDescription()
 * @method null|array getWeather()
 * @method null|string getWeatherValue()
 * @method null|string getWeatherDescription()
 * @method null|array getTimeOfDay()
 * @method null|string getTimeOfDayValue()
 * @method null|string getTimeOfDayDescription()
 * @method null|array getSeason()
 * @method null|string getSeasonValue()
 * @method null|string getSeasonDescription()
 * @method null|array getMood()
 * @method null|string getMoodValue()
 * @method null|string getMoodDescription()
 * @method null|array getConstraints()
 * @method null|array getConstraintsValue()
 * @method null|string getConstraintsDescription()
 * @method null|array getCharacterContexts()
 * @method null|array getCharacterContextsValue()
 * @method null|string getCharacterContextsDescription()
 * @method null|array getObjectContexts()
 * @method null|array getObjectContextsValue()
 * @method null|string getObjectContextsDescription()
 * @method null|array getAbstractionContexts()
 * @method null|array getAbstractionContextsValue()
 * @method null|string getAbstractionContextsDescription()
 */
class LandscapeContext extends SemanticContext
{
    use HasAbstractionContexts;
    use HasCharacterContexts;
    use HasObjectContexts;

    public function setSetting(string $setting, ?float $weight = null): static
    {
        return $this->set(
            'setting',
            'Primary environment setting (e.g., city street, forest trail, mountain ridge).',
            $setting,
            $weight
        );
    }

    public function setLocation(string $location, ?float $weight = null): static
    {
        return $this->set(
            'location',
            'Specific location identity or regional cues for the landscape.',
            $location,
            $weight
        );
    }

    public function setTerrain(string $terrain, ?float $weight = null): static
    {
        return $this->set(
            'terrain',
            'Ground and topography characteristics of the scene.',
            $terrain,
            $weight
        );
    }

    public function setVegetation(string $vegetation, ?float $weight = null): static
    {
        return $this->set(
            'vegetation',
            'Plant life and natural growth details in the landscape.',
            $vegetation,
            $weight
        );
    }

    public function setStructures(array $structures, ?float $weight = null): static
    {
        return $this->set(
            'structures',
            'Built structures or architectural elements present in the scene.',
            array_values(array_filter($structures, fn (mixed $structure): bool => is_string($structure) && $structure !== '')),
            $weight
        );
    }

    public function setWeather(string $weather, ?float $weight = null): static
    {
        return $this->set(
            'weather',
            'Weather conditions affecting mood and visibility.',
            $weather,
            $weight
        );
    }

    public function setTimeOfDay(string $timeOfDay, ?float $weight = null): static
    {
        return $this->set(
            'time_of_day',
            'Time-of-day context shaping light direction and atmosphere.',
            $timeOfDay,
            $weight
        );
    }

    public function setSeason(string $season, ?float $weight = null): static
    {
        return $this->set(
            'season',
            'Seasonal context affecting color, climate, and environment cues.',
            $season,
            $weight
        );
    }

    public function setMood(string $mood, ?float $weight = null): static
    {
        return $this->set(
            'mood',
            'Overall emotional atmosphere of the landscape scene.',
            $mood,
            $weight
        );
    }

    public function setConstraints(array $constraints, ?float $weight = null): static
    {
        return $this->set(
            'constraints',
            'Landscape-specific hard requirements or exclusions.',
            array_values(array_filter($constraints, fn (mixed $constraint): bool => is_string($constraint) && $constraint !== '')),
            $weight
        );
    }
}