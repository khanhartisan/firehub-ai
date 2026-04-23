<?php

namespace Tests\Unit\Contracts\CommonData;

use App\Contracts\CommonData\Verification;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    public function test_it_serializes_using_snake_case_keys(): void
    {
        $verification = (new Verification)
            ->setIsValid(true)
            ->setConfidence(0.93)
            ->setReasoning('Claim is supported by the cited primary source.');

        $payload = $verification->toArray();

        $this->assertSame([
            'is_valid' => true,
            'confidence' => 0.93,
            'reasoning' => 'Claim is supported by the cited primary source.',
        ], $payload);
    }

    public function test_it_hydrates_from_snake_case_payload(): void
    {
        $verification = Verification::fromArray([
            'is_valid' => false,
            'confidence' => 0.41,
            'reasoning' => 'The source does not support the stated number.',
        ]);

        $this->assertFalse($verification->getIsValid());
        $this->assertSame(0.41, $verification->getConfidence());
        $this->assertSame('The source does not support the stated number.', $verification->getReasoning());
    }
}
