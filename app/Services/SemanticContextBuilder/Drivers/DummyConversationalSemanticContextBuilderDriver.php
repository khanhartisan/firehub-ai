<?php

namespace App\Services\SemanticContextBuilder\Drivers;

use App\Concerns\Contextable;
use App\Contracts\CommonData\SemanticContext;
use App\Contracts\SemanticContextBuilder\ConversationalSemanticContextBuilder;

class DummyConversationalSemanticContextBuilderDriver implements ConversationalSemanticContextBuilder
{
    use Contextable;

    /** @var array<int, array{role: string, text: string}> */
    protected array $conversation = [];

    public function __construct(?SemanticContext $context = null)
    {
        $this->setContext($context ?? new SemanticContext());
    }

    public function setContext(?SemanticContext $context): static
    {
        $this->context = ($context ?? new SemanticContext())->withEmptyFields(true);

        return $this;
    }

    public function start(string $seedMessage): static
    {
        $this->conversation = [];

        return $this->continueWith($seedMessage);
    }

    public function continueWith(string $userMessage): static
    {
        $text = trim($userMessage);
        if ($text === '') {
            return $this;
        }

        $this->conversation[] = [
            'role' => 'user',
            'text' => $text,
        ];

        $this->applyUserMessageToContext($text);

        $pendingQuestions = $this->getPendingQuestions();
        if ($pendingQuestions !== []) {
            $formattedQuestions = [];
            foreach ($pendingQuestions as $index => $question) {
                $formattedQuestions[] = ($index + 1).'. '.$question;
            }

            $this->conversation[] = [
                'role' => 'assistant',
                'text' => implode("\n", $formattedQuestions),
            ];
        }

        return $this;
    }

    public function isFulfilled(): bool
    {
        return $this->getPendingQuestions() === [];
    }

    public function getNextQuestion(): ?string
    {
        $pending = $this->getPendingQuestions();

        return $pending[0] ?? null;
    }

    public function getPendingQuestions(): array
    {
        $questions = [];

        foreach ($this->context->toArray() as $key => $entry) {
            if (! array_key_exists('value', $entry) || ! $this->isEmptyValue($entry['value'])) {
                continue;
            }

            $description = is_string($entry['description'] ?? null) ? trim($entry['description']) : '';
            if ($description !== '') {
                $questions[] = $description;
                continue;
            }

            $questions[] = 'Please provide value for "'.$key.'".';
        }

        return $questions;
    }

    public function getConversation(): array
    {
        return $this->conversation;
    }

    protected function applyUserMessageToContext(string $text): void
    {
        $rows = preg_split('/\R+/', $text) ?: [];

        foreach ($rows as $row) {
            $row = trim($row);
            if ($row === '' || ! str_contains($row, ':')) {
                continue;
            }

            [$rawKey, $rawValue] = array_map('trim', explode(':', $row, 2));
            $key = $this->normalizeKey($rawKey);
            if ($key === '' || ! $this->context->has($key)) {
                continue;
            }

            $description = $this->context->getDescription($key) ?? ('User-provided value for '.$key.'.');
            $this->context->set($key, $description, $this->parseValue($rawValue));
        }
    }

    protected function normalizeKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';

        return trim($key, '_');
    }

    protected function parseValue(string $value): string|array|null
    {
        $value = trim($value);
        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        if (str_contains($value, ',')) {
            $items = array_values(array_filter(
                array_map(static fn (string $item): string => trim($item), explode(',', $value)),
                static fn (string $item): bool => $item !== ''
            ));

            return $items;
        }

        return $value;
    }

    protected function isEmptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }
}
