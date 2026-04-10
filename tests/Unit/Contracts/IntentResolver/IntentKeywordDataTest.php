<?php

namespace Tests\Unit\Contracts\IntentResolver;

use App\Contracts\IntentResolver\IntentKeywordData;
use PHPUnit\Framework\TestCase;

class IntentKeywordDataTest extends TestCase
{
    public function test_to_array_and_from_array_are_symmetric(): void
    {
        $data = (new IntentKeywordData)
            ->setKeyword('  best running shoes  ')
            ->setRelevance(0.85);

        $roundTrip = IntentKeywordData::fromArray($data->toArray());

        $this->assertSame('best running shoes', $roundTrip->getKeyword());
        $this->assertSame(0.85, $roundTrip->getRelevance());
        $this->assertSame($data->toArray(), $roundTrip->toArray());
    }

    public function test_from_array_accepts_null_relevance(): void
    {
        $row = IntentKeywordData::fromArray([
            'keyword' => 'buy shoes',
            'relevance' => null,
        ]);

        $this->assertSame('buy shoes', $row->getKeyword());
        $this->assertNull($row->getRelevance());
    }

    public function test_set_keyword_rejects_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new IntentKeywordData)->setKeyword('   ');
    }

    public function test_from_array_requires_keyword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IntentKeywordData::fromArray(['relevance' => 0.5]);
    }
}
