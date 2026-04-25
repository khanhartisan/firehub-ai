<?php

namespace Tests\Unit\Contracts\CommonData;

use App\Contracts\CommonData\Point;
use App\Contracts\CommonData\Verification;
use Tests\TestCase;

class PointTest extends TestCase
{
    public function test_it_serializes_with_verification_object(): void
    {
        $point = (new Point)
            ->setHeadline('Signal is stable')
            ->setDescription('The trend appears across multiple quarters.')
            ->setEvidences(['Q1 and Q2 reports align'])
            ->setVerification(
                (new Verification)
                    ->setIsValid(true)
                    ->setConfidence(0.86)
                    ->setReasoning('Cross-checked against primary sources.')
            )->addMeta('source', 'test-source');

        $payload = $point->toArray();

        $this->assertSame([
            'headline' => 'Signal is stable',
            'description' => 'The trend appears across multiple quarters.',
            'evidences' => ['Q1 and Q2 reports align'],
            'verification' => [
                'is_valid' => true,
                'confidence' => 0.86,
                'reasoning' => 'Cross-checked against primary sources.',
            ],
            'meta' => [
                'source' => 'test-source',
            ],
        ], $payload);
    }
}
