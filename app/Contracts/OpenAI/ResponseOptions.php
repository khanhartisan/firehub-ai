<?php

namespace App\Contracts\OpenAI;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;

/**
 * Options for an OpenAI Responses API request (model, tools, temperature, etc.).
 *
 * Built via fluent setters or fromArray(); toArray() produces the payload for the API.
 * Used when calling OpenAIClient::createResponse().
 */
final class ResponseOptions implements Serializable
{
    use SerializableTrait;

    protected ?string $model = null;

    /** For multi-turn: ID of the previous response to continue from. */
    protected ?string $previousResponseId = null;

    /** @var array<int, array<string, mixed>>|null Tool definitions for the model */
    protected ?array $tools = null;

    protected string|array|null $toolChoice = null;

    protected ?array $responseFormat = null;

    protected ?float $temperature = null;

    protected ?int $maxTokens = null;

    protected ?int $maxOutputTokens = null;

    protected ?int $maxToolCalls = null;

    protected ?bool $parallelToolCalls = null;

    protected ?float $topP = null;

    protected ?int $topLogprobs = null;

    protected ?string $truncation = null;

    protected ?string $instructions = null;

    protected ?bool $store = null;

    protected ?bool $background = null;

    /**
     * Create a new instance.
     */
    public static function create(): self
    {
        return new self;
    }

    /**
     * Set the model to use.
     */
    public function model(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set the previous response ID for multi-turn conversations.
     */
    public function previousResponseId(string $previousResponseId): self
    {
        $this->previousResponseId = $previousResponseId;

        return $this;
    }

    /**
     * Set the tools available to the model.
     *
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function tools(array $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    /**
     * Set how the model should select tools.
     *
     * @param  string|array<string, mixed>  $toolChoice
     */
    public function toolChoice(string|array $toolChoice): self
    {
        $this->toolChoice = $toolChoice;

        return $this;
    }

    /**
     * Set the response format (e.g., JSON schema).
     */
    public function responseFormat(array $responseFormat): self
    {
        $this->responseFormat = $responseFormat;

        return $this;
    }

    /**
     * Set the temperature (0-2).
     */
    public function temperature(float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }

    /**
     * Set the maximum number of tokens to generate.
     */
    public function maxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    /**
     * Set the maximum number of output tokens.
     */
    public function maxOutputTokens(int $maxOutputTokens): self
    {
        $this->maxOutputTokens = $maxOutputTokens;

        return $this;
    }

    /**
     * Set the maximum number of tool calls.
     */
    public function maxToolCalls(int $maxToolCalls): self
    {
        $this->maxToolCalls = $maxToolCalls;

        return $this;
    }

    /**
     * Set whether to allow parallel tool calls.
     */
    public function parallelToolCalls(bool $parallelToolCalls): self
    {
        $this->parallelToolCalls = $parallelToolCalls;

        return $this;
    }

    /**
     * Set the top_p sampling parameter (0-1).
     */
    public function topP(float $topP): self
    {
        $this->topP = $topP;

        return $this;
    }

    /**
     * Set the number of most likely tokens to return at each position.
     */
    public function topLogprobs(int $topLogprobs): self
    {
        $this->topLogprobs = $topLogprobs;

        return $this;
    }

    /**
     * Set the truncation strategy ('auto' or 'disabled').
     */
    public function truncation(string $truncation): self
    {
        $this->truncation = $truncation;

        return $this;
    }

    /**
     * Set system/developer instructions.
     */
    public function instructions(string $instructions): self
    {
        $this->instructions = $instructions;

        return $this;
    }

    /**
     * Set whether to store the response.
     */
    public function store(bool $store): self
    {
        $this->store = $store;

        return $this;
    }

    /**
     * Set whether to run the response in the background.
     */
    public function background(bool $background): self
    {
        $this->background = $background;

        return $this;
    }

    /**
     * Convert to array format for API request.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $options = [];

        if ($this->model !== null) {
            $options['model'] = $this->model;
        }

        if ($this->previousResponseId !== null) {
            $options['previous_response_id'] = $this->previousResponseId;
        }

        if ($this->tools !== null) {
            $options['tools'] = $this->tools;
        }

        if ($this->toolChoice !== null) {
            $options['tool_choice'] = $this->toolChoice;
        }

        if ($this->responseFormat !== null) {
            $options['response_format'] = $this->responseFormat;
        }

        if ($this->temperature !== null) {
            $options['temperature'] = $this->temperature;
        }

        if ($this->maxTokens !== null) {
            $options['max_tokens'] = $this->maxTokens;
        }

        if ($this->maxOutputTokens !== null) {
            $options['max_output_tokens'] = $this->maxOutputTokens;
        }

        if ($this->maxToolCalls !== null) {
            $options['max_tool_calls'] = $this->maxToolCalls;
        }

        if ($this->parallelToolCalls !== null) {
            $options['parallel_tool_calls'] = $this->parallelToolCalls;
        }

        if ($this->topP !== null) {
            $options['top_p'] = $this->topP;
        }

        if ($this->topLogprobs !== null) {
            $options['top_logprobs'] = $this->topLogprobs;
        }

        if ($this->truncation !== null) {
            $options['truncation'] = $this->truncation;
        }

        if ($this->instructions !== null) {
            $options['instructions'] = $this->instructions;
        }

        if ($this->store !== null) {
            $options['store'] = $this->store;
        }

        if ($this->background !== null) {
            $options['background'] = $this->background;
        }

        return $options;
    }

    /**
     * Get the model.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Get the previous response ID.
     */
    public function getPreviousResponseId(): ?string
    {
        return $this->previousResponseId;
    }

    /**
     * Get the tools.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function getTools(): ?array
    {
        return $this->tools;
    }

    /**
     * Get the tool choice.
     *
     * @return string|array<string, mixed>|null
     */
    public function getToolChoice(): string|array|null
    {
        return $this->toolChoice;
    }

    /**
     * Get the response format.
     *
     * @return array<string, mixed>|null
     */
    public function getResponseFormat(): ?array
    {
        return $this->responseFormat;
    }

    /**
     * Get the temperature.
     */
    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    /**
     * Get the max tokens.
     */
    public function getMaxTokens(): ?int
    {
        return $this->maxTokens;
    }

    /**
     * Create an instance from an array representation.
     *
     * @param  array<string, mixed>  $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $options = static::create();

        if (isset($data['model'])) {
            $options->model($data['model']);
        }

        if (isset($data['previous_response_id'])) {
            $options->previousResponseId($data['previous_response_id']);
        }

        if (isset($data['tools'])) {
            $options->tools($data['tools']);
        }

        if (isset($data['tool_choice'])) {
            $options->toolChoice($data['tool_choice']);
        }

        if (isset($data['response_format'])) {
            $options->responseFormat($data['response_format']);
        }

        if (isset($data['temperature'])) {
            $options->temperature($data['temperature']);
        }

        if (isset($data['max_tokens'])) {
            $options->maxTokens($data['max_tokens']);
        }

        if (isset($data['max_output_tokens'])) {
            $options->maxOutputTokens($data['max_output_tokens']);
        }

        if (isset($data['max_tool_calls'])) {
            $options->maxToolCalls($data['max_tool_calls']);
        }

        if (isset($data['parallel_tool_calls'])) {
            $options->parallelToolCalls($data['parallel_tool_calls']);
        }

        if (isset($data['top_p'])) {
            $options->topP($data['top_p']);
        }

        if (isset($data['top_logprobs'])) {
            $options->topLogprobs($data['top_logprobs']);
        }

        if (isset($data['truncation'])) {
            $options->truncation($data['truncation']);
        }

        if (isset($data['instructions'])) {
            $options->instructions($data['instructions']);
        }

        if (isset($data['store'])) {
            $options->store($data['store']);
        }

        if (isset($data['background'])) {
            $options->background($data['background']);
        }

        return $options;
    }
}
