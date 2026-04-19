<?php

namespace Tests\Unit\Services\Synthesizer\IdeaForge;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\OpenAIIdeaAuditorDriver;
use Mockery;
use Tests\TestCase;

class OpenAIIdeaAuditorDriverTest extends TestCase
{
    public function test_audit_parses_structured_response(): void
    {
        $payload = json_encode([
            'score' => 0.77,
            'highlights' => ['Clear angle', 'Strong audience fit'],
            'concerns' => ['Needs a sharper CTA'],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_audit',
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

        $idea = $this->makeIdea();

        $driver = new OpenAIIdeaAuditorDriver($client, ['model' => 'gpt-4o-mini']);
        $report = $driver->audit($idea);

        $this->assertSame(0.77, $report->getScore());
        $this->assertSame(['Clear angle', 'Strong audience fit'], $report->getHighlights());
        $this->assertSame(['Needs a sharper CTA'], $report->getConcerns());
    }

    protected function makeIdea(): Idea
    {
        $intent = (new Intent)
            ->setTitle('Test title')
            ->setDescription('Test description for audit.')
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::EVERGREEN)
            ->setTypes([IntentType::INFORMATIONAL]);

        return new Idea($intent, 0.6, 'test');
    }
}
