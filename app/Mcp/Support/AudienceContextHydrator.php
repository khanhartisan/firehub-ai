<?php

namespace App\Mcp\Support;

use App\Contracts\CommonData\AudienceContext;

final class AudienceContextHydrator
{
    /**
     * @return AudienceContext[]
     */
    public static function fromArray(mixed $rawAudienceContexts): array
    {
        if (! is_array($rawAudienceContexts)) {
            return [];
        }

        $audienceContexts = [];

        foreach ($rawAudienceContexts as $row) {
            if (! is_array($row) || $row === []) {
                continue;
            }

            $audienceContext = new AudienceContext;

            foreach ($row as $key => $value) {
                if (! is_string($key)) {
                    continue;
                }

                $description = $audienceContext->getDescription($key) ?? ('Audience context field: '.$key);
                $audienceContext->set($key, $description, $value);
            }

            $audienceContexts[] = $audienceContext;
        }

        return $audienceContexts;
    }
}
