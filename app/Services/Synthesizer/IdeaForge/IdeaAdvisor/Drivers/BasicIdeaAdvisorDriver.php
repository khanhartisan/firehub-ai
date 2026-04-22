<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers;

use App\Contracts\CommonData\SemanticContext;
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
    public function __construct(
        ?string $identifier = 'basic-idea-advisor',
        ?string $description = 'A demo-dummy advisor for temporal and intent suggestions in testing mode.'
    ) {
        $this->setIdentifier($identifier);
        $this->setDescription($description);
    }

    public function suggestTemporal(string $clientId, SemanticContext $context): array
    {
        $contextText = mb_strtolower(trim($this->contextToText($context)));

        $weighted = [
            ['temporal' => Temporal::EVERGREEN, 'confidence' => 0.55],
            ['temporal' => Temporal::TOPICAL, 'confidence' => 0.45],
            ['temporal' => Temporal::TRENDING, 'confidence' => 0.40],
        ];

        if (str_contains($contextText, 'today') || str_contains($contextText, 'now') || str_contains($contextText, 'latest')) {
            $weighted[] = ['temporal' => Temporal::BREAKING, 'confidence' => 0.85];
            $weighted[] = ['temporal' => Temporal::TRENDING, 'confidence' => 0.70];
        }

        if (str_contains($contextText, 'season') || str_contains($contextText, 'holiday') || str_contains($contextText, 'summer')) {
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

    public function suggestIntentTypes(string $clientId, SemanticContext $context): array
    {
        $contextText = mb_strtolower(trim($this->contextToText($context)));

        $weighted = [
            ['intent_type' => IntentType::INFORMATIONAL, 'confidence' => 0.70],
            ['intent_type' => IntentType::COMMERCIAL, 'confidence' => 0.45],
            ['intent_type' => IntentType::TRANSACTIONAL, 'confidence' => 0.35],
            ['intent_type' => IntentType::NAVIGATIONAL, 'confidence' => 0.30],
            ['intent_type' => IntentType::LOCAL, 'confidence' => 0.25],
        ];

        if (str_contains($contextText, 'buy') || str_contains($contextText, 'price') || str_contains($contextText, 'cost')) {
            $weighted[] = ['intent_type' => IntentType::TRANSACTIONAL, 'confidence' => 0.85];
            $weighted[] = ['intent_type' => IntentType::COMMERCIAL, 'confidence' => 0.70];
        }

        if (str_contains($contextText, 'near me') || str_contains($contextText, 'in ') || str_contains($contextText, 'local')) {
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
        SemanticContext $context,
        int $limit = 5
    ): array {
        $temporals = array_values(array_filter($temporalSuggestions, static fn ($item) => $item instanceof TemporalSuggestion));
        $intentTypes = array_values(array_filter($intentTypeSuggestions, static fn ($item) => $item instanceof IntentTypeSuggestion));

        if ($temporals === [] || $intentTypes === []) {
            return [];
        }

        $contextText = trim($this->contextToText($context));

        $ideas = [];
        foreach ($temporals as $temporalSuggestion) {
            foreach ($intentTypes as $intentTypeSuggestion) {
                if (count($ideas) >= $limit) {
                    break 2;
                }

                $intent = (new Intent)
                    ->setTitle(sprintf('%s angle for %s', ucfirst($temporalSuggestion->getTemporal()->value), $intentTypeSuggestion->getIntentType()->name))
                    ->setDescription($contextText)
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

    protected function contextToText(SemanticContext $context): string
    {
        return json_encode($context->toArray(), JSON_UNESCAPED_UNICODE) ?: '';
    }
}
