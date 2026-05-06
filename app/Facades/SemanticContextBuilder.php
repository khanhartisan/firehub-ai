<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Contracts\SemanticContextBuilder\ConversationalSemanticContextBuilder setContext(\App\Contracts\CommonData\SemanticContext $context)
 * @method static \App\Contracts\CommonData\SemanticContext getContext()
 * @method static \App\Contracts\SemanticContextBuilder\ConversationalSemanticContextBuilder start(string $seedMessage)
 * @method static \App\Contracts\SemanticContextBuilder\ConversationalSemanticContextBuilder continueWith(string $userMessage)
 * @method static bool isFulfilled()
 * @method static string|null getNextQuestion()
 * @method static string[] getPendingQuestions()
 * @method static array<int, array{role: string, text: string}> getConversation()
 * @method static \App\Contracts\SemanticContextBuilder\ConversationalSemanticContextBuilder driver(string|null $driver = null)
 *
 * @see \App\Services\SemanticContextBuilder\SemanticContextBuilderManager
 */
class SemanticContextBuilder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'semantic_context_builder.manager';
    }
}
