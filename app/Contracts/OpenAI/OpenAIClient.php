<?php

namespace App\Contracts\OpenAI;

/**
 * Client for OpenAI Responses API–compatible endpoints.
 *
 * Used by PageClassifier, PageParser, ScrapePolicyEngine, and FileVision drivers
 * to send prompts and receive model output. Configure via config/openai.php.
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
