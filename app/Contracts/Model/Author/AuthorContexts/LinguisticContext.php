<?php

namespace App\Contracts\Model\Author\AuthorContexts;

use App\Contracts\CommonData\SemanticContext;

class LinguisticContext extends SemanticContext
{
    public function setVocabularyTier(string $vocabularyTier): static
    {
        return $this->set(
            'vocabulary_tier',
            'The complexity and register of the words used. (Example: Colloquial, Standard, Academic, Esoteric...)',
            $vocabularyTier
        );
    }

    public function setJargonDomains(array $jargonDomains): static
    {
        return $this->set(
            'jargon_domains',
            'Specific professional or cultural domains from which the author draws their terminology.',
            $jargonDomains
        );
    }

    public function setSentenceBurstinessIndex(string|float $sentenceBurstinessIndex): static
    {
        return $this->set(
            'sentence_burstiness_index',
            'A float from 0.0 to 1.0. Represents variance in sentence length. High value MUST force the AI to mix extremely short, punchy sentences (2-5 words) with very long, complex ones to destroy the typical LLM monotonous rhythm.',
            $sentenceBurstinessIndex
        );
    }

    public function setPreferredRhetoricalDevices(array $preferredRhetoricalDevices): static
    {
        return $this->set(
            'preferred_rhetorical_devices',
            'Figures of speech the author overuses to make a point. e.g., ["Sarcasm", "Extended Metaphor"]. Author may inject these devices into the prose.',
            $preferredRhetoricalDevices
        );
    }

    public function setDefaultTone(string $defaultTone): static
    {
        return $this->set(
            'default_tone',
            'The author baseline emotional state of the writing. e.g., "Sardonic but instructive"... The author usually maintain this vibe throughout.',
            $defaultTone
        );
    }

    public function setPassionTrigger(string $passionTrigger): static
    {
        return $this->set(
            'passion_trigger',
            'Specific topics or triggers that make the author drop their default tone and become fiercely passionate, angry, or emotional...',
            $passionTrigger
        );
    }

    public function setForbiddenPatterns(array $forbiddenPatterns): static
    {
        return $this->set(
            'forbidden_patterns',
            'Phrases the AI MUST NEVER GENERATE under any circumstances. Use this to ban typical LLM clichés like "In conclusion", "It is important to remember", or "A testament to".',
            $forbiddenPatterns
        );
    }
}