<?php

namespace Tests\Unit\Contracts\IntentResolver;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\IntentResolver\IntentKeyword;
use App\Enums\IntentType;
use App\Enums\Language;
use PHPUnit\Framework\TestCase;

class IntentKeywordDataTest extends TestCase
{
    public function test_to_array_and_from_array_are_symmetric(): void
    {
        $intent = (new Intent)
            ->setTitle('T')
            ->setDescription(str_repeat('x', 100))
            ->setLanguage(Language::EN)
            ->setTypes([IntentType::INFORMATIONAL]);

        $data = (new IntentKeyword)
            ->setIntent($intent)
            ->setKeyword('  best running shoes  ')
            ->setRelevance(0.85);

        $roundTrip = IntentKeyword::fromArray($data->toArray());

        $this->assertSame('best running shoes', $roundTrip->getKeyword());
        $this->assertSame(0.85, $roundTrip->getRelevance());
        $this->assertSame($data->toArray(), $roundTrip->toArray());
    }

    public function test_from_array_accepts_null_relevance(): void
    {
        $intent = (new Intent)
            ->setTitle('T')
            ->setDescription(str_repeat('x', 100))
            ->setLanguage(Language::EN)
            ->setTypes([IntentType::INFORMATIONAL]);

        $row = IntentKeyword::fromArray([
            'intent' => $intent->toArray(),
            'keyword' => 'buy shoes',
            'relevance' => null,
        ]);

        $this->assertSame('buy shoes', $row->getKeyword());
        $this->assertEquals(0, intval($row->getRelevance() * 100));
    }

    public function test_set_keyword_rejects_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new IntentKeyword)->setKeyword('   ');
    }

    public function test_from_array_requires_keyword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IntentKeyword::fromArray(['relevance' => 0.5]);
    }
}
