<?php

namespace App\Mcp\Server\Methods;

use App\Mcp\Exceptions\McpToolException;
use Generator;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\Errable;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Throwable;

class AppCallTool extends CallTool
{
    /**
     * @return JsonRpcResponse|Generator<JsonRpcResponse>
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        try {
            return parent::handle($request, $context);
        } catch (McpToolException $exception) {
            $tool = $context
                ->tools()
                ->first(
                    fn ($tool): bool => $tool->name() === $request->params['name'],
                    fn () => throw new JsonRpcException(
                        "Tool [{$request->params['name']}] not found.",
                        -32602,
                        $request->id,
                    ),
                );

            return $this->toJsonRpcResponse(
                $request,
                Response::error($exception->getMessage()),
                $this->serializable($tool),
            );
        }
    }

    protected function callHandler(callable $handler, JsonRpcRequest $request): mixed
    {
        try {
            return $handler();
        } catch (McpToolException $exception) {
            return Response::error($exception->getMessage());
        } catch (Throwable $throwable) {
            if ($this instanceof Errable) {
                return $this->toErrorResponse($throwable);
            }

            throw $this->toJsonRpcException($throwable, $request->id);
        }
    }
}
