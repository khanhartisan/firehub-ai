<?php

namespace Tests\Unit\Contracts\IntentResolver;

use App\Contracts\IntentResolver\IntentData;
use App\Contracts\IntentResolver\IntentKeywordsData;
use App\Contracts\IntentResolver\KeywordData;
use App\Enums\IntentType;
use App\Enums\Language;
use PHPUnit\Framework\TestCase;

class IntentKeywordsDataTest extends TestCase
{
    public function test_to_array_and_from_array_are_symmetric(): void
    {
        $intent = (new IntentData)
            ->setTitle('Test intent')
            ->setDescription(str_repeat('x', 100))
            ->setLanguage(Language::EN)
            ->setTypes([IntentType::INFORMATIONAL]);

        $kw = (new KeywordData)
            ->setKeyword('running shoes')
            ->setRelevance(0.9);

        $bundle = (new IntentKeywordsData)
            ->setIntent($intent)
            ->setKeywords([$kw]);

        $roundTrip = IntentKeywordsData::fromArray($bundle->toArray());

        $this->assertEquals($bundle->toArray(), $roundTrip->toArray());
        $this->assertSame('Test intent', $roundTrip->getIntent()->getTitle());
        $this->assertCount(1, $roundTrip->getKeywords());
        $this->assertSame('running shoes', $roundTrip->getKeywords()[0]->getKeyword());
    }

    public function test_set_keywords_rejects_invalid_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $intent = (new IntentData)
            ->setTitle('Test intent')
            ->setDescription(str_repeat('x', 100))
            ->setLanguage(Language::EN)
            ->setTypes([IntentType::INFORMATIONAL]);

        (new IntentKeywordsData)
            ->setIntent($intent)
            ->setKeywords(['not-keyword-data']);
    }
}
