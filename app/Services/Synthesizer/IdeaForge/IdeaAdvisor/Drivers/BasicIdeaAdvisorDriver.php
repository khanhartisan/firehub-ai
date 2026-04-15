<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\IdeaAdvisorService;

class BasicIdeaAdvisorDriver extends IdeaAdvisorService
{
    public function suggestTemporal(string $clientId, string $context): array
    {
        $context = mb_strtolower(trim($context));

        $weighted = [
            ['temporal' => Temporal::EVERGREEN, 'confidence' => 0.55],
            ['temporal' => Temporal::TOPICAL, 'confidence' => 0.45],
            ['temporal' => Temporal::TRENDING, 'confidence' => 0.40],
        ];

        if (str_contains($context, 'today') || str_contains($context, 'now') || str_contains($context, 'latest')) {
            $weighted[] = ['temporal' => Temporal::BREAKING, 'confidence' => 0.85];
            $weighted[] = ['temporal' => Temporal::TRENDING, 'confidence' => 0.70];
        }

        if (str_contains($context, 'season') || str_contains($context, 'holiday') || str_contains($context, 'summer')) {
            $weighted[] = ['temporal' => Temporal::SEASONAL, 'confidence' => 0.80];
        }

        usort(
            $weighted,
            static fn (array $left, array $right): int => $right['confidence'] <=> $left['confidence']
        );

        return array_map(
            static fn (array $item) => new TemporalSuggestion(
                $item['temporal'],
                $item['confidence'],
                sprintf('Inferred from context for client %s.', $clientId)
            ),
            $weighted
        );
    }

    public function suggestIntentTypes(string $clientId, string $context): array
    {
        $context = mb_strtolower(trim($context));

        $weighted = [
            ['intent_type' => IntentType::INFORMATIONAL, 'confidence' => 0.70],
            ['intent_type' => IntentType::COMMERCIAL, 'confidence' => 0.45],
            ['intent_type' => IntentType::TRANSACTIONAL, 'confidence' => 0.35],
            ['intent_type' => IntentType::NAVIGATIONAL, 'confidence' => 0.30],
            ['intent_type' => IntentType::LOCAL, 'confidence' => 0.25],
        ];

        if (str_contains($context, 'buy') || str_contains($context, 'price') || str_contains($context, 'cost')) {
            $weighted[] = ['intent_type' => IntentType::TRANSACTIONAL, 'confidence' => 0.85];
            $weighted[] = ['intent_type' => IntentType::COMMERCIAL, 'confidence' => 0.70];
        }

        if (str_contains($context, 'near me') || str_contains($context, 'in ') || str_contains($context, 'local')) {
            $weighted[] = ['intent_type' => IntentType::LOCAL, 'confidence' => 0.80];
        }

        usort(
            $weighted,
            static fn (array $left, array $right): int => $right['confidence'] <=> $left['confidence']
        );

        return array_map(
            static fn (array $item) => new IntentTypeSuggestion(
                $item['intent_type'],
                $item['confidence'],
                sprintf('Inferred from context for client %s.', $clientId)
            ),
            $weighted
        );
    }

    public function brainstorm(
        array $temporalSuggestions,
        array $intentTypeSuggestions,
        string $context,
        int $limit = 5
    ): array {
        $temporals = array_values(array_filter($temporalSuggestions, static fn ($item) => $item instanceof TemporalSuggestion));
        $intentTypes = array_values(array_filter($intentTypeSuggestions, static fn ($item) => $item instanceof IntentTypeSuggestion));

        if ($temporals === [] || $intentTypes === []) {
            return [];
        }

        $ideas = [];
        foreach ($temporals as $temporalSuggestion) {
            foreach ($intentTypes as $intentTypeSuggestion) {
                if (count($ideas) >= $limit) {
                    break 2;
                }

                $intent = (new Intent)
                    ->setTitle(sprintf('%s angle for %s', ucfirst($temporalSuggestion->getTemporal()->value), $intentTypeSuggestion->getIntentType()->name))
                    ->setDescription($context)
                    ->setTemporal($temporalSuggestion->getTemporal())
                    ->setLanguage(Language::EN)
                    ->setTypes([$intentTypeSuggestion->getIntentType()]);

                $confidence = max(
                    0.0,
                    min(
                        1.0,
                        (($temporalSuggestion->getConfidence() ?? 0.5) + ($intentTypeSuggestion->getConfidence() ?? 0.5)) / 2
                    )
                );

                $ideas[] = new Idea(
                    intent: $intent,
                    confidence: $confidence,
                    reason: 'Generated from ranked temporal and intent type suggestions.'
                );
            }
        }

        return $ideas;
    }
}
