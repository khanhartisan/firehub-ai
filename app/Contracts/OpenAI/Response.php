<?php

namespace App\Contracts\OpenAI;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;
use App\Enums\OpenAI\ResponseStatus;

/**
 * Wrapper for a single Responses API response (id, status, output, usage).
 *
 * Built from API JSON; use getOutputText() or getFirstOutputText() to get
 * extracted text from message/output_text content for parsing.
 */
final class Response implements Serializable
{
    use SerializableTrait;

    protected string $id;

    protected int $createdAt;

    protected ResponseStatus $status;

    protected ?int $completedAt = null;

    /** @var array<string, mixed>|null Error details when status is FAILED */
    protected ?array $error = null;

    protected string $model;

    /** @var array<int, array<string, mixed>> API output items (e.g. message with content) */
    protected array $output = [];

    /** @var array<string, mixed>|null Token usage (input_tokens, output_tokens, etc.) */
    protected ?array $usage = null;

    /**
     * Create a Response from API data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $response = new static();

        $response->id = $data['id'] ?? '';
        $response->createdAt = $data['created_at'] ?? 0;
        $response->status = ResponseStatus::tryFrom($data['status'] ?? 'unknown') ?? ResponseStatus::INCOMPLETE;
        $response->completedAt = $data['completed_at'] ?? null;
        $response->error = $data['error'] ?? null;
        $response->model = $data['model'] ?? '';
        $response->output = $data['output'] ?? [];
        $response->usage = $data['usage'] ?? null;

        return $response;
    }

    /**
     * Get the response ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the creation timestamp.
     */
    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    /**
     * Get the status.
     */
    public function getStatus(): ResponseStatus
    {
        return $this->status;
    }

    /**
     * Check if the response is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === ResponseStatus::COMPLETED;
    }

    /**
     * Check if the response failed.
     */
    public function isFailed(): bool
    {
        return $this->status === ResponseStatus::FAILED;
    }

    /**
     * Check if the response is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === ResponseStatus::IN_PROGRESS;
    }

    /**
     * Check if the response was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === ResponseStatus::CANCELLED;
    }

    /**
     * Check if the response is queued.
     */
    public function isQueued(): bool
    {
        return $this->status === ResponseStatus::QUEUED;
    }

    /**
     * Check if the response is incomplete.
     */
    public function isIncomplete(): bool
    {
        return $this->status === ResponseStatus::INCOMPLETE;
    }

    /**
     * Get the completion timestamp.
     */
    public function getCompletedAt(): ?int
    {
        return $this->completedAt;
    }

    /**
     * Get the error, if any.
     *
     * @return array<string, mixed>|null
     */
    public function getError(): ?array
    {
        return $this->error;
    }

    /**
     * Get the model used.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get all output items.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOutput(): array
    {
        return $this->output;
    }

    /**
     * Get the output text from the response.
     * Extracts text from output_text items in the output array.
     */
    public function getOutputText(): string
    {
        $texts = [];

        foreach ($this->output as $item) {
            if (($item['type'] ?? null) === 'message' && isset($item['content'])) {
                foreach ($item['content'] as $content) {
                    if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                        $texts[] = $content['text'];
                    }
                }
            }
        }

        return implode("\n", $texts);
    }

    /**
     * Get the first output text, if available.
     */
    public function getFirstOutputText(): ?string
    {
        foreach ($this->output as $item) {
            if (($item['type'] ?? null) === 'message' && isset($item['content'])) {
                foreach ($item['content'] as $content) {
                    if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                        return $content['text'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get token usage information.
     *
     * @return array<string, mixed>|null
     */
    public function getUsage(): ?array
    {
        return $this->usage;
    }

    /**
     * Get input tokens count.
     */
    public function getInputTokens(): ?int
    {
        return $this->usage['input_tokens'] ?? null;
    }

    /**
     * Get output tokens count.
     */
    public function getOutputTokens(): ?int
    {
        return $this->usage['output_tokens'] ?? null;
    }

    /**
     * Get total tokens count.
     */
    public function getTotalTokens(): ?int
    {
        return $this->usage['total_tokens'] ?? null;
    }

    /**
     * Convert the response to an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->createdAt,
            'status' => $this->status->value,
            'completed_at' => $this->completedAt,
            'error' => $this->error,
            'model' => $this->model,
            'output' => $this->output,
            'usage' => $this->usage,
        ];
    }
}
