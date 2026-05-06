<?php

namespace App\Services\SemanticContextBuilder;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\SemanticContextBuilder\ConversationalSemanticContextBuilder;
use Illuminate\Support\Manager;

class SemanticContextBuilderManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('semantic_context_builder.default', 'dummy');
    }

    protected function createDummyDriver(): ConversationalSemanticContextBuilder
    {
        return new Drivers\DummyConversationalSemanticContextBuilderDriver();
    }

    protected function createOpenaiDriver(): ConversationalSemanticContextBuilder
    {
        $config = $this->config->get('semantic_context_builder.drivers.openai', []);

        return new Drivers\OpenAIConversationalSemanticContextBuilderDriver(
            $this->container->make(OpenAIClient::class),
            $config
        );
    }
}
