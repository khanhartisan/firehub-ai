<?php

namespace App\Services\Synthesizer\BriefBuilder\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Services\Synthesizer\BriefBuilder\BriefBuilderService;

class OpenAIBriefBuilderDriver extends BriefBuilderService
{
    public function conceive(Idea $idea, SemanticContext $context): Brief
    {
        // TODO: Need improvement to make the best use of the AI
        $fallbackDescription = $this->resolveFallbackDescription($context);

        return (new Brief)
            ->setTemporal($idea->getIntent()->getTemporal())
            ->setTitle($idea->getIntent()->getTitle())
            ->setDescription($idea->getIntent()->getDescription() ?: $fallbackDescription)
            ->setInstructions([
                'Draft with a strong narrative arc and concrete examples.',
                'Use clear, concise language tailored to the target audience.',
            ]);
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
