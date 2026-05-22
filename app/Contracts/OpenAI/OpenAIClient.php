<?php

namespace App\Contracts\OpenAI;

/**
 * Client for model inference with a unified response shape.
 *
 * Implemented by {@see \App\Services\OpenAI\OpenAIService} (Responses API) and
 * {@see \App\Services\OpenAI\Drivers\ChatCompletionsDriver} (/chat/completions).
 * Configure via config/openai.php.
 */
interface OpenAIClient
{
    /**
     * Create a model response.
     *
     * @param  ResponseInput  $input  The input for the model
     * @param  ResponseOptions|null  $options  Additional options (model, previous_response_id, etc.)
     * @return Response  The response object
     */
    public function createResponse(ResponseInput $input, ?ResponseOptions $options = null): Response;

    /**
     * Get a previously created response.
     *
     * @param  string  $responseId  The response ID
     * @return Response  The response object
     */
    public function getResponse(string $responseId): Response;

    /**
     * Cancel an in-progress response.
     *
     * @param  string  $responseId  The response ID
     * @return Response  The cancellation result
     */
    public function cancelResponse(string $responseId): Response;

    /**
     * Delete a response.
     *
     * @param  string  $responseId  The response ID
     * @return Response  The deletion result
     */
    public function deleteResponse(string $responseId): Response;
}
