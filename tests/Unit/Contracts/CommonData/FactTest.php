<?php

namespace Tests\Unit\Contracts\CommonData;

use App\Contracts\CommonData\Fact;
use App\Contracts\CommonData\Verification;
use Tests\TestCase;

class FactTest extends TestCase
{
    public function test_it_serializes_fact_with_verification(): void
    {
        $fact = (new Fact('Open-source adoption rose year over year.'))
            ->setVerification(
                (new Verification)
                    ->setIsValid(true)
                    ->setConfidence(0.89)
                    ->setReasoning('Supported by the provided quarterly report.')
            );

        $this->assertSame([
            'fact' => 'Open-source adoption rose year over year.',
            'verification' => [
                'is_valid' => true,
                'confidence' => 0.89,
                'reasoning' => 'Supported by the provided quarterly report.',
            ],
        ], $fact->toArray());
    }

    public function test_it_hydrates_from_payload(): void
    {
        $fact = Fact::fromArray([
            'fact' => '  Incident count decreased by 17%.  ',
            'verification' => [
                'is_valid' => true,
                'confidence' => 0.92,
                'reasoning' => 'Claim aligns with the provided incident log.',
            ],
        ]);

        $this->assertSame('Incident count decreased by 17%.', $fact->getFact());
        $this->assertInstanceOf(Verification::class, $fact->getVerification());
        $this->assertTrue($fact->getVerification()?->getIsValid());
        $this->assertSame(0.92, $fact->getVerification()?->getConfidence());
    }

    public function test_it_requires_non_empty_fact_on_instantiation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Fact(" \n\t ");
    }
}
