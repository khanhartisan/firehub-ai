<?php

namespace App\Contracts\SemanticContextBuilder;

use App\Contracts\CommonData\SemanticContext;

/**
 * Service contract for progressively fulfilling a semantic context
 * through multi-turn conversation with a user.
 */
interface ConversationalSemanticContextBuilder
{
    /**
     * Set or replace the target semantic context being fulfilled.
     */
    public function setContext(SemanticContext $context): static;

    /**
     * Get the latest context snapshot.
     */
    public function getContext(): SemanticContext;

    /**
     * Handle an initial user seed message and start the conversation.
     */
    public function start(string $seedMessage): static;

    /**
     * Handle one user reply in the ongoing conversation.
     */
    public function continueWith(string $userMessage): static;

    /**
     * Whether context has enough data to be considered fulfilled.
     */
    public function isFulfilled(): bool;

    /**
     * The next best question to ask the user, if any.
     */
    public function getNextQuestion(): ?string;

    /**
     * Ordered list of currently pending clarification questions.
     *
     * @return string[]
     */
    public function getPendingQuestions(): array;

    /**
     * Full conversation history in chronological order.
     *
     * @return array<int, array{role: string, text: string}>
     */
    public function getConversation(): array;
}
