<?php

namespace Tests\Unit\Contracts\IntentResolver;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\IntentResolver\Intentable;
use App\Contracts\IntentResolver\IntentableIntent;
use App\Contracts\IntentResolver\IntentableIntents;
use App\Enums\IntentType;
use App\Enums\Language;
use PHPUnit\Framework\TestCase;

class IntentableIntentsTest extends TestCase
{
    public function test_to_array_and_from_array_are_symmetric(): void
    {
        $intentA = (new Intent)
            ->setTitle('First intent')
            ->setDescription(str_repeat('x', 100))
            ->setLanguage(Language::EN)
            ->setTypes([IntentType::COMMERCIAL]);

        $intentB = (new Intent)
            ->setTitle('Second intent')
            ->setDescription(str_repeat('y', 100))
            ->setLanguage(Language::EN)
            ->setTypes([IntentType::INFORMATIONAL]);

        $snippet = (new Intentable)->setContent('First snippet.');

        $a = (new IntentableIntent)
            ->setIntent($intentA)
            ->setIntentable($snippet)
            ->setRelevance(0.5);

        $b = (new IntentableIntent)
            ->setIntent($intentB)
            ->setIntentable($snippet)
            ->setRelevance(0.9);

        $bundle = (new IntentableIntents)
            ->setIntentableIntents([$a, $b]);

        $roundTrip = IntentableIntents::fromArray($bundle->toArray());

        $this->assertEquals($bundle->toArray(), $roundTrip->toArray());
        $this->assertCount(2, $roundTrip->getIntentableIntents());
        $this->assertSame('First snippet.', $roundTrip->getIntentableIntents()[0]->getIntentable()?->getContent());
        $this->assertSame(0.9, $roundTrip->getIntentableIntents()[1]->getRelevance());
        $this->assertSame('Second intent', $roundTrip->getPrimaryIntent()?->getTitle());
    }

    public function test_from_array_requires_at_least_one_valid_row(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IntentableIntents::fromArray(['intentable_intents' => []]);
    }

    public function test_set_intentable_intents_rejects_invalid_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new IntentableIntents)
            ->setIntentableIntents(['not-an-intentable-intent']);
    }
}
