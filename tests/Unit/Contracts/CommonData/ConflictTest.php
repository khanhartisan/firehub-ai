<?php

namespace Tests\Unit\Contracts\CommonData;

use App\Contracts\CommonData\Conflict;
use App\Contracts\CommonData\Fact;
use Tests\TestCase;

class ConflictTest extends TestCase
{
    public function test_it_serializes_facts_and_rationale(): void
    {
        $conflict = (new Conflict)
            ->setFacts([
                new Fact('Claim A'),
                new Fact('Claim B'),
            ])
            ->setRationale('Sources disagree on the underlying metric definition.');

        $this->assertSame([
            'facts' => [
                ['fact' => 'Claim A', 'verification' => null],
                ['fact' => 'Claim B', 'verification' => null],
            ],
            'rationale' => 'Sources disagree on the underlying metric definition.',
        ], $conflict->toArray());
    }

    public function test_it_hydrates_from_payload(): void
    {
        $conflict = Conflict::fromArray([
            'facts' => [
                ['fact' => 'alpha'],
                ['fact' => '123'],
                ['fact' => 'true'],
            ],
            'rationale' => 'Conflicting values were observed.',
        ]);

        $this->assertCount(3, $conflict->getFacts());
        $this->assertSame('alpha', $conflict->getFacts()[0]->getFact());
        $this->assertSame('123', $conflict->getFacts()[1]->getFact());
        $this->assertSame('true', $conflict->getFacts()[2]->getFact());
        $this->assertSame('Conflicting values were observed.', $conflict->getRationale());
    }
}
