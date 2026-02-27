<?php

namespace Tests\Unit\Contracts\OpenAI;

use App\Contracts\OpenAI\ResponseOptions;
use Tests\TestCase;

class ResponseOptionsTest extends TestCase
{
    public function test_it_creates_empty_options(): void
    {
        $options = ResponseOptions::create();

        $this->assertEmpty($options->toArray());
    }

    public function test_it_sets_model(): void
    {
        $options = ResponseOptions::create()
            ->model('gpt-4o');

        $this->assertEquals('gpt-4o', $options->getModel());
        $this->assertEquals(['model' => 'gpt-4o'], $options->toArray());
    }

    public function test_it_sets_previous_response_id(): void
    {
        $options = ResponseOptions::create()
            ->previousResponseId('resp_123');

        $this->assertEquals('resp_123', $options->getPreviousResponseId());
        $array = $options->toArray();
        $this->assertEquals('resp_123', $array['previous_response_id']);
    }

    public function test_it_sets_tools(): void
    {
        $tools = [
            ['type' => 'web_search'],
            ['type' => 'file_search'],
        ];

        $options = ResponseOptions::create()
            ->tools($tools);

        $this->assertEquals($tools, $options->getTools());
        $array = $options->toArray();
        $this->assertEquals($tools, $array['tools']);
    }

    public function test_it_sets_tool_choice(): void
    {
        $options = ResponseOptions::create()
            ->toolChoice('auto');

        $this->assertEquals('auto', $options->getToolChoice());
        $array = $options->toArray();
        $this->assertEquals('auto', $array['tool_choice']);
    }

    public function test_it_sets_tool_choice_as_array(): void
    {
        $toolChoice = ['type' => 'function', 'function' => ['name' => 'test']];

        $options = ResponseOptions::create()
            ->toolChoice($toolChoice);

        $this->assertEquals($toolChoice, $options->getToolChoice());
    }

    public function test_it_sets_response_format(): void
    {
        $format = ['type' => 'json_schema', 'name' => 'test', 'schema' => ['type' => 'object'], 'strict' => true];

        $options = ResponseOptions::create()
            ->responseFormat($format);

        $this->assertEquals($format, $options->getResponseFormat());
        $array = $options->toArray();
        $this->assertEquals($format, $array['response_format']);
    }

    public function test_it_sets_temperature(): void
    {
        $options = ResponseOptions::create()
            ->temperature(0.7);

        $this->assertEquals(0.7, $options->getTemperature());
        $array = $options->toArray();
        $this->assertEquals(0.7, $array['temperature']);
    }

    public function test_it_sets_max_tokens(): void
    {
        $options = ResponseOptions::create()
            ->maxTokens(1000);

        $this->assertEquals(1000, $options->getMaxTokens());
        $array = $options->toArray();
        $this->assertEquals(1000, $array['max_tokens']);
    }

    public function test_it_sets_max_output_tokens(): void
    {
        $options = ResponseOptions::create()
            ->maxOutputTokens(500);

        $array = $options->toArray();
        $this->assertEquals(500, $array['max_output_tokens']);
    }

    public function test_it_sets_max_tool_calls(): void
    {
        $options = ResponseOptions::create()
            ->maxToolCalls(10);

        $array = $options->toArray();
        $this->assertEquals(10, $array['max_tool_calls']);
    }

    public function test_it_sets_parallel_tool_calls(): void
    {
        $options = ResponseOptions::create()
            ->parallelToolCalls(false);

        $array = $options->toArray();
        $this->assertFalse($array['parallel_tool_calls']);
    }

    public function test_it_sets_top_p(): void
    {
        $options = ResponseOptions::create()
            ->topP(0.9);

        $array = $options->toArray();
        $this->assertEquals(0.9, $array['top_p']);
    }

    public function test_it_sets_top_logprobs(): void
    {
        $options = ResponseOptions::create()
            ->topLogprobs(5);

        $array = $options->toArray();
        $this->assertEquals(5, $array['top_logprobs']);
    }

    public function test_it_sets_truncation(): void
    {
        $options = ResponseOptions::create()
            ->truncation('auto');

        $array = $options->toArray();
        $this->assertEquals('auto', $array['truncation']);
    }

    public function test_it_sets_instructions(): void
    {
        $options = ResponseOptions::create()
            ->instructions('You are a helpful assistant.');

        $array = $options->toArray();
        $this->assertEquals('You are a helpful assistant.', $array['instructions']);
    }

    public function test_it_sets_store(): void
    {
        $options = ResponseOptions::create()
            ->store(false);

        $array = $options->toArray();
        $this->assertFalse($array['store']);
    }

    public function test_it_sets_background(): void
    {
        $options = ResponseOptions::create()
            ->background(true);

        $array = $options->toArray();
        $this->assertTrue($array['background']);
    }

    public function test_it_only_includes_set_options(): void
    {
        $options = ResponseOptions::create()
            ->model('gpt-4o')
            ->temperature(0.7);

        $array = $options->toArray();

        $this->assertArrayHasKey('model', $array);
        $this->assertArrayHasKey('temperature', $array);
        $this->assertArrayNotHasKey('max_tokens', $array);
        $this->assertArrayNotHasKey('tools', $array);
    }

    public function test_it_supports_fluent_chaining(): void
    {
        $options = ResponseOptions::create()
            ->model('gpt-4o')
            ->temperature(0.7)
            ->maxTokens(1000)
            ->previousResponseId('resp_123');

        $array = $options->toArray();

        $this->assertEquals('gpt-4o', $array['model']);
        $this->assertEquals(0.7, $array['temperature']);
        $this->assertEquals(1000, $array['max_tokens']);
        $this->assertEquals('resp_123', $array['previous_response_id']);
    }
}
