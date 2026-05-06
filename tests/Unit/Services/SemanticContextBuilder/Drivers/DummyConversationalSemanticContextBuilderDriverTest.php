<?php

namespace Tests\Unit\Services\SemanticContextBuilder\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Services\SemanticContextBuilder\Drivers\DummyConversationalSemanticContextBuilderDriver;
use Tests\TestCase;

class DummyConversationalSemanticContextBuilderDriverTest extends TestCase
{
    public function test_driver_tracks_pending_questions_and_updates_context_from_key_value_input(): void
    {
        $context = new class() extends SemanticContext {
            public function setName(string $name): static
            {
                return $this->set('name', 'What is the channel name?', $name);
            }

            public function setNiches(array $niches): static
            {
                return $this->set('niches', 'Which niches does it target?', $niches);
            }
        };

        $driver = new DummyConversationalSemanticContextBuilderDriver($context);

        $this->assertFalse($driver->isFulfilled());
        $this->assertSame('What is the channel name?', $driver->getNextQuestion());

        $driver->start("name: Atlas Weekly\nniches: ai, automation");

        $this->assertTrue($driver->isFulfilled());
        $this->assertSame('Atlas Weekly', $driver->getContext()->getNameValue());
        $this->assertSame(['ai', 'automation'], $driver->getContext()->getNichesValue());
        $this->assertCount(1, $driver->getConversation());
    }

    public function test_driver_adds_assistant_follow_up_when_not_fulfilled(): void
    {
        $context = new class() extends SemanticContext {
            public function setName(string $name): static
            {
                return $this->set('name', 'What is the channel name?', $name);
            }
        };

        $driver = new DummyConversationalSemanticContextBuilderDriver($context);
        $driver->start('hello there');

        $conversation = $driver->getConversation();

        $this->assertCount(2, $conversation);
        $this->assertSame('user', $conversation[0]['role']);
        $this->assertSame('assistant', $conversation[1]['role']);
        $this->assertSame('What is the channel name?', $conversation[1]['text']);
    }
}
