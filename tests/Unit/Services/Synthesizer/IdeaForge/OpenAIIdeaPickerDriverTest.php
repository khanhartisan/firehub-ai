<?php

namespace Tests\Unit\Services\Synthesizer\IdeaForge;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\OpenAIIdeaPickerDriver;
use Mockery;
use Tests\TestCase;

class OpenAIIdeaPickerDriverTest extends TestCase
{
    public function test_pick_single_report_does_not_call_openai(): void
    {
        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldNotReceive('createResponse');

        $driver = new OpenAIIdeaPickerDriver($client, ['model' => 'gpt-4o-mini']);
        $report = $this->makeAuditReport('Only', 0.5);

        $picked = $driver->pick([$report], 'context', 1);

        $this->assertNotNull($picked);
        $this->assertCount(1, $picked);
        $this->assertSame($report, $picked[0]);
    }

    public function test_pick_follows_structured_response_order(): void
    {
        $low = $this->makeAuditReport('Low score', 0.2);
        $high = $this->makeAuditReport('High score', 0.95);

        $payload = json_encode([
            'picked_audit_report_identifiers' => [
                $high->getIdentifier(),
                $low->getIdentifier(),
            ],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_pick',
            'created_at' => time(),
            'status' => 'completed',
            'model' => 'gpt-4o-mini',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => $payload,
                        ],
                    ],
                ],
            ],
        ]);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')->once()->andReturn($response);

        $driver = new OpenAIIdeaPickerDriver($client, ['model' => 'gpt-4o-mini']);
        $picked = $driver->pick([$low, $high], 'editorial context', 2);

        $this->assertNotNull($picked);
        $this->assertCount(2, $picked);
        $this->assertSame($high, $picked[0]);
        $this->assertSame($low, $picked[1]);
    }

    public function test_pick_empty_model_list_falls_back_to_score_desc(): void
    {
        $low = $this->makeAuditReport('Low', 0.2);
        $high = $this->makeAuditReport('High', 0.95);

        $payload = json_encode([
            'picked_audit_report_identifiers' => [],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_pick',
            'created_at' => time(),
            'status' => 'completed',
            'model' => 'gpt-4o-mini',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => $payload,
                        ],
                    ],
                ],
            ],
        ]);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')->once()->andReturn($response);

        $driver = new OpenAIIdeaPickerDriver($client, ['model' => 'gpt-4o-mini']);
        $picked = $driver->pick([$low, $high], 'context', 1);

        $this->assertNotNull($picked);
        $this->assertCount(1, $picked);
        $this->assertSame($high, $picked[0]);
    }

    protected function makeAuditReport(string $title, float $score): IdeaAuditReport
    {
        $intent = (new Intent)
            ->setTitle($title)
            ->setDescription('Description for '.$title)
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::TOPICAL)
            ->setTypes([IntentType::INFORMATIONAL]);

        $idea = new Idea($intent, 0.5, 'id-'.$title);

        return new IdeaAuditReport($idea, $score, ['h'], []);
    }
}
