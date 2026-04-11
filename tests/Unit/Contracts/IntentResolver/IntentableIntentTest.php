<?php

namespace Tests\Unit\Contracts\IntentResolver;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\IntentResolver\Intentable;
use App\Contracts\IntentResolver\IntentableIntent;
use App\Enums\IntentType;
use App\Enums\Language;
use PHPUnit\Framework\TestCase;

class IntentableIntentTest extends TestCase
{
    public function test_to_array_and_from_array_are_symmetric(): void
    {
        $intent = (new Intent)
            ->setTitle('T')
            ->setDescription(str_repeat('d', 100))
            ->setLanguage(Language::EN)
            ->setTypes([IntentType::INFORMATIONAL]);

        $intentable = (new Intentable)->setContent('Page body text for scoring.');

        $row = (new IntentableIntent)
            ->setIntent($intent)
            ->setIntentable($intentable)
            ->setRelevance(0.72);

        $roundTrip = IntentableIntent::fromArray($row->toArray());

        $this->assertEquals($row->toArray(), $roundTrip->toArray());
        $this->assertSame('T', $roundTrip->getIntent()->getTitle());
        $this->assertSame('Page body text for scoring.', $roundTrip->getIntentable()?->getContent());
        $this->assertSame(0.72, $roundTrip->getRelevance());
    }

    public function test_from_array_requires_intent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IntentableIntent::fromArray(['intentable' => ['content' => 'x']]);
    }
}
