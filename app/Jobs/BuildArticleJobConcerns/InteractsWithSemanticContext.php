<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\CommonData\SemanticContext;

trait InteractsWithSemanticContext
{
    protected function buildSemanticContext(): ?SemanticContext
    {
        $context = new SemanticContext;
        $context->set(
            'client_language',
            'The primary language the client uses to publish contents',
            $this->client->language?->value
        );
        $hasAny = false;

        if ($this->client->context) {
            $clientContextPayload = $this->client->context->toArray();
            $hasClientContextValue = false;
            foreach ($clientContextPayload as $entry) {
                if (is_array($entry)
                    && array_key_exists('value', $entry)
                    && $this->contextPayloadHasContent($entry['value'])
                ) {
                    $hasClientContextValue = true;
                    break;
                }
            }

            if ($hasClientContextValue) {
                $context->set('client_context', 'Client context DTO payload.', $clientContextPayload);
                $hasAny = true;
            }
        }

        if ($this->article?->context) {
            $articleContextPayload = $this->article->context->toArray();
            $hasArticleContextValue = false;
            foreach ($articleContextPayload as $entry) {
                if (is_array($entry)
                    && array_key_exists('value', $entry)
                    && $this->contextPayloadHasContent($entry['value'])
                ) {
                    $hasArticleContextValue = true;
                    break;
                }
            }

            if ($hasArticleContextValue) {
                $context->set('article_context', 'Article-specific context DTO payload.', $articleContextPayload);
                $hasAny = true;
            }
        }

        return $hasAny ? $context : null;
    }

    protected function contextPayloadHasContent(mixed $payload): bool
    {
        if ($payload === null) {
            return false;
        }

        if (is_string($payload)) {
            return trim($payload) !== '';
        }

        if (is_int($payload) || is_float($payload) || is_bool($payload)) {
            return true;
        }

        if (! is_array($payload)) {
            return false;
        }

        foreach ($payload as $value) {
            if ($this->contextPayloadHasContent($value)) {
                return true;
            }
        }

        return false;
    }
}

