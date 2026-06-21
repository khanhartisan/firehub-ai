<?php

namespace Tests\Unit\Services\Synthesizer\Tagger;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Services\Synthesizer\Tagger\Drivers\OpenAITaggerDriver;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OpenAITaggerDriverTest extends TestCase
{
    public function test_suggest_tags_without_client_throws(): void
    {
        $driver = new OpenAITaggerDriver;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI client is not configured');

        $driver->suggestTags('How AI coding tools improve software team delivery velocity.');
    }

    public function test_suggest_tags_uses_structured_response_and_normalizes_output(): void
    {
        $payload = json_encode([
            'tags' => [' AI ', 'developer tools', 'ai', 'Productivity '],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_tagger_1',
            'created_at' => time(),
            'status' => 'completed',
            'model' => 'gpt-4o-mini',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => $payload,
                ]],
            ]],
        ]);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')
            ->once()
            ->withArgs(function ($input, $options) {
                $prompt = $input->toArray()[0]['content'][0]['text'] ?? '';

                return str_contains($prompt, '"recent_tags":["ai","developer tools"]');
            })
            ->andReturn($response);

        $driver = new OpenAITaggerDriver($client, ['model' => 'gpt-4o-mini', 'max_tags' => 3]);

        $tags = $driver->suggestTags(
            'How AI coding tools improve software team delivery velocity.',
            ['ai', 'developer tools'],
            (new SemanticContext)->set('voice', 'Author voice', 'Operator-first and pragmatic')
        );

        $this->assertSame(['ai', 'developer tools', 'productivity'], $tags);
    }

    public function test_suggest_tags_for_empty_content_returns_untagged(): void
    {
        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldNotReceive('createResponse');

        $driver = new OpenAITaggerDriver($client);

        $tags = $driver->suggestTags('   ');

        $this->assertSame(['untagged'], $tags);
    }
}
