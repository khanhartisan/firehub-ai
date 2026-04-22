<?php

namespace App\Services\Synthesizer\BriefBuilder\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Services\Synthesizer\BriefBuilder\BriefBuilderService;

class BasicBriefBuilderDriver extends BriefBuilderService
{
    public function conceive(Idea $idea, SemanticContext $context): Brief
    {
        $intent = $idea->getIntent();
        $fallbackDescription = $this->resolveFallbackDescription($context);
        $instructions = array_filter([
            'Keep claims grounded in source context.',
            'Keep structure concise and scannable.',
            $idea->getReason(),
        ]);

        return (new Brief)
            ->setTemporal($intent->getTemporal())
            ->setTitle($intent->getTitle())
            ->setDescription($intent->getDescription() ?: $fallbackDescription)
            ->setInstructions(array_values($instructions));
    }

    protected function resolveFallbackDescription(SemanticContext $context): string
    {
        $articleContext = $context->getArticleContextValue();
        if (is_string($articleContext) || is_int($articleContext) || is_float($articleContext)) {
            return trim((string) $articleContext);
        }

        if (is_array($articleContext)) {
            $rawText = $articleContext['meta']['value']['raw_text'] ?? null;
            if (is_string($rawText)) {
                return trim($rawText);
            }

            return trim(json_encode($articleContext, JSON_UNESCAPED_UNICODE) ?: '');
        }

        $description = $context->getDescriptionValue();
        return is_string($description) ? trim($description) : '';
    }
}
