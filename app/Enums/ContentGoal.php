<?php

namespace App\Enums;

use App\Contracts\DescribableEnum;

enum ContentGoal: string implements DescribableEnum
{
    /**
     * To teach or explain concepts.
     *
     * Sample:
     * "A Comprehensive Guide to Generative AI:
     * How Transformers are Revolutionizing Virtual Apparel Try-ons."
     *
     * Focus: Clarity, definitions, and step-by-step logic.
     */
    case EDUCATE = 'educate';

    /**
     * Positions the brand or creator as a leader/expert
     * by sharing deep insights, data, or unique perspectives.
     *
     * Sample:
     * "The 2026 State of AI in E-commerce:
     * Why 85% of Plus-Size Retailers are Switching to Virtual Models."
     *
     * Focus: Professionalism, data-driven arguments, and industry jargon.
     */
    case AUTHORITY = 'authority';

    /**
     * Drives a specific action, usually a sale, a sign-up, or a download.
     *
     * Sample:
     * "Stop Losing Sales to Poor Fit.
     * Integrate SampleBrand Today and Boost Your Conversion Rate by 40%."
     *
     * Focus: Persuasion, benefits-over-features, and strong Calls to Action (CTA).
     */
    case CONVERT = 'convert';

    /**
     * Captures attention through humor, curiosity, or emotional engagement.
     *
     * Sample:
     * "Expectation vs. Reality:
     * 5 Times Online Shopping Went Horribly Wrong (And How AI Fixes It)."
     *
     * Focus: Wit, storytelling, and relatable "hooks."
     */
    case ENTERTAIN = 'entertain';

    /**
     * To challenge the status quo or spark debate.
     *
     * Sample:
     * "Body Positivity is Dead if Your Size Charts are
     * Still Based on 1950s Data. It’s Time for a Paradigm Shift."
     *
     * Focus: Bold statements, rhetorical questions, and controversial stances.
     */
    case PROVOKE = 'provoke';

    /**
     * Builds a brand-audience bond by sharing human-centric narratives and journeys.
     *
     * Sample:
     * "Behind the Code: How a Team of Three Engineers
     * Built a Global Solution for Plus-Size Fashion."
     *
     * Focus: Character arc, emotional resonance, and "The Hero's Journey."
     */
    case STORYTELLING = 'storytelling';

    /**
     * Provides factual, concise, and timely updates or news.
     *
     * Sample: "Breaking news:..."
     *
     * Focus: Brevity, neutrality, and a high "signal-to-noise" ratio.
     */
    case INFORM = 'inform';

    public static function describe(DescribableEnum $enum): string
    {
        return match ($enum) {
            self::EDUCATE => 'To teach or explain concepts. Focus: Clarity, definitions, and step-by-step logic.',
            self::AUTHORITY => 'Positions the brand or creator as a leader/expert by sharing deep insights, data, or unique perspectives. Focus: Professionalism, data-driven arguments, and industry jargon.',
            self::CONVERT => 'Drives a specific action, usually a sale, a sign-up, or a download... Focus: Persuasion, benefits-over-features, and strong Calls to Action (CTA).',
            self::ENTERTAIN => 'Captures attention through humor, curiosity, or emotional engagement. Focus: Wit, storytelling, and relatable "hooks."',
            self::PROVOKE => 'To challenge the status quo or spark debate. Focus: Bold statements, rhetorical questions, and controversial stances.',
            self::STORYTELLING => 'Builds a brand-audience bond by sharing human-centric narratives and journeys. Focus: Character arc, emotional resonance, and "The Hero\'s Journey."',
            self::INFORM => 'Provides factual, concise, and timely updates or news. Focus: Brevity, neutrality, and a high "signal-to-noise" ratio.',
        };
    }
}