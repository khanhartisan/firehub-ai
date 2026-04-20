<?php

namespace Tests\Unit\Contracts\IntentResolver;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\IntentResolver\IntentKeyword;
use App\Contracts\IntentResolver\IntentKeywords;
use App\Enums\IntentType;
use App\Enums\Language;
use PHPUnit\Framework\TestCase;

class IntentKeywordsDataTest extends TestCase
{
    public function test_to_array_and_from_array_are_symmetric(): void
    {
        $intent = (new Intent)
            ->setTitle('Test intent')
            ->setDescription(str_repeat('x', 100))
            ->setLanguage(Language::EN)
            ->setTypes([IntentType::INFORMATIONAL]);

        $kw = (new IntentKeyword)
            ->setIntent($intent)
            ->setKeyword('running shoes')
            ->setRelevance(0.9);

        $bundle = (new IntentKeywords)
            ->setIntent($intent)
            ->setIntentKeywords([$kw]);

        $roundTrip = IntentKeywords::fromArray($bundle->toArray());

        $this->assertEquals($bundle->toArray(), $roundTrip->toArray());
        $this->assertSame('Test intent', $roundTrip->getIntent()->getTitle());
        $this->assertCount(1, $roundTrip->getIntentKeywords());
        $this->assertSame('running shoes', $roundTrip->getIntentKeywords()[0]->getKeyword());
    }

    public function test_set_keywords_rejects_invalid_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $intent = (new Intent)
            ->setTitle('Test intent')
            ->setDescription(str_repeat('x', 100))
            ->setLanguage(Language::EN)
            ->setTypes([IntentType::INFORMATIONAL]);

        (new IntentKeywords)
            ->setIntent($intent)
            ->setIntentKeywords(['not-keyword-data']);
    }
}
